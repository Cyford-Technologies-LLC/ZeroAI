import uvicorn
from fastapi import FastAPI, HTTPException, Depends, Form, UploadFile, File, Request
from fastapi.responses import JSONResponse
from pydantic import BaseModel
from typing import Dict, Any, List, Optional
from pathlib import Path
from rich.console import Console
import sys
import shutil
import base64
import os
import time
import json
from crewai import CrewOutput, TaskOutput
from pydantic import json as pydantic_json  # Import Pydantic's JSON tool


# Define a placeholder class for UsageMetrics since it's removed in new CrewAI versions
class UsageMetrics:
    def __init__(self, total_tokens=0, prompt_tokens=0, completion_tokens=0, successful_requests=0):
        self.total_tokens = total_tokens
        self.prompt_tokens = prompt_tokens
        self.completion_tokens = completion_tokens
        self.successful_requests = successful_requests


# Disable CrewAI telemetry by setting the environment variable
os.environ['CREWAI_DISABLE_TELEMETRY'] = "true"

# Ensure src directory is in the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from peer_discovery import PeerDiscovery
from distributed_router import DistributedRouter
from ai_crew import AICrewManager
from cache_manager import cache

console = Console()

peer_discovery_instance = PeerDiscovery()
distributed_router = DistributedRouter(peer_discovery_instance)

app = FastAPI(
    title="CrewAI Endpoint API",
    description="API to expose CrewAI crews as endpoints.",
    version="1.0.0",
)


# Add a logging middleware to inspect all requests and responses
@app.middleware("http")
async def log_requests(request: Request, call_next):
    start_time = time.time()
    console.print(f"[bold yellow]--- Incoming Request ---[/bold yellow]")
    console.print(f"[yellow]Path:[/yellow] {request.url.path}")
    console.print(f"[yellow]Method:[/yellow] {request.method}")
    console.print(f"[yellow]Headers:[/yellow] {dict(request.headers)}")

    if request.url.path == "/run_crew_ai_json/":
        try:
            body_bytes = await request.body()
            body_decoded = body_bytes.decode("utf-8")
            console.print(f"[yellow]Body:[/yellow] {body_decoded}")

            async def receive() -> dict:
                return {"type": "http.request", "body": body_bytes}

            request._receive = receive
        except Exception as e:
            console.print(f"[yellow]Body:[/yellow] Error decoding body: {e}", style="dim")

    response = await call_next(request)

    process_time = time.time() - start_time
    response_body = b""
    async for chunk in response.body_iterator:
        response_body += chunk

    try:
        response_data = json.loads(response_body.decode("utf-8"))
        console.print(f"[bold green]--- Outgoing Response ---[/bold green]")
        console.print(f"[green]Status Code:[/green] {response.status_code}")
        console.print(f"[green]Processing Time:[/green] {process_time:.4f}s")
        console.print(f"[green]Response Body:[/green] {json.dumps(response_data, indent=2)}")
        console.print(f"[bold yellow]-------------------------[/bold yellow]")
        return JSONResponse(content=response_data, status_code=response.status_code)
    except (json.JSONDecodeError, UnicodeDecodeError):
        console.print(f"[bold green]--- Outgoing Response ---[/bold green]")
        console.print(f"[green]Status Code:[/green] {response.status_code}")
        console.print(f"[green]Processing Time:[/green] {process_time:.4f}s")
        console.print(f"[green]Response Body:[/green] {response_body.decode('utf-8', errors='ignore')}")
        console.print(f"[bold yellow]-------------------------[/bold yellow]")
        return JSONResponse(content=response_body.decode('utf-8', errors='ignore'), status_code=response.status_code,
                            media_type="text/plain")


class FileData(BaseModel):
    name: str
    type: str
    base64_data: str


class CrewRequest(BaseModel):
    inputs: Dict[str, Any]


def get_distributed_router():
    return distributed_router


def crew_output_to_dict(crew_output: CrewOutput) -> Dict[str, Any]:
    """
    Converts a CrewOutput object to a dictionary for JSON serialization
    by converting it to a JSON string and then back to a dictionary.
    """
    if not isinstance(crew_output, CrewOutput):
        return crew_output

    # Use pydantic's built-in JSON encoder to handle complex types
    json_string = pydantic_json.dumps(crew_output)
    return json.loads(json_string)


def task_output_to_dict(task_output: TaskOutput) -> Dict[str, Any]:
    """Converts a TaskOutput object to a dictionary for JSON serialization."""
    # Use pydantic's built-in JSON encoder for TaskOutput
    json_string = pydantic_json.dumps(task_output)
    return json.loads(json_string)


def usage_metrics_to_dict(usage_metrics: UsageMetrics) -> Dict[str, Any]:
    """Converts a UsageMetrics object to a dictionary for JSON serialization."""
    return {
        "total_tokens": usage_metrics.total_tokens,
        "prompt_tokens": usage_metrics.prompt_tokens,
        "completion_tokens": usage_metrics.completion_tokens,
        "successful_requests": usage_metrics.successful_requests
    }


def process_crew_request(inputs: Dict[str, Any], uploaded_files_paths: List[str]):
    """
    Handles the core logic for running the AI crew and returns the complete CrewOutput as JSON.
    """
    try:
        topic = inputs.get("topic")
        category = inputs.get("category", "general")

        if not topic:
            raise ValueError("Missing required 'topic' input.")

        inputs['file_content'] = ""
        if uploaded_files_paths:
            file_contents = []
            for file_path in uploaded_files_paths:
                try:
                    with open(file_path, 'r') as f:
                        file_contents.append(f.read())
                except Exception as e:
                    console.print(f"❌ Error reading file {file_path}: {e}", style="red")
                    file_contents.append(f"Error reading uploaded file: {os.path.basename(file_path)}")
            inputs['file_content'] = "\n\n".join(file_contents)

        inputs['files'] = uploaded_files_paths

        console.print(f"✅ Received API Request:", style="green")
        console.print(f"   Topic: {topic}")
        console.print(f"   Category: {category}")
        console.print(f"   AI Provider: {inputs.get('ai_provider')}")
        console.print(f"   Model Name: {inputs.get('model_name')}")
        console.print(f"   Uploaded Files: {[os.path.basename(f) for f in uploaded_files_paths]}")

        manager = AICrewManager(distributed_router, inputs=inputs, category=category)
        crew = manager.create_crew_for_category(inputs)

        cache_key = f"{category}_{topic}_{inputs.get('context', '')}_{inputs.get('research_focus', '')}_{inputs.get('ai_provider', '')}_{inputs.get('model_name', '')}"

        cached_response = cache.get(cache_key, "crew_result")

        response_data = None
        if cached_response:
            console.print(f"✅ Cache Hit. Processing result...", style="blue")
            if isinstance(cached_response, dict):
                response_data = cached_response
            elif isinstance(cached_response, CrewOutput):
                console.print(f"⚠️ Cache hit with non-serializable CrewOutput object. Converting.", style="yellow")
                response_data = crew_output_to_dict(cached_response)
                cache.set(cache_key, "crew_result", response_data)
            else:
                console.print(
                    f"❌ Cache contained an unexpected data type: {type(cached_response)}. Executing new crew.",
                    style="red")

        if not response_data:
            console.print(f"⚠️ No valid data from cache. Executing AI Crew...", style="yellow")
            crew_output = manager.execute_crew(crew, inputs)
            response_data = crew_output_to_dict(crew_output)
            cache.set(cache_key, "crew_result", response_data)

        if not isinstance(response_data, dict):
            console.print(f"❌ Final response data is not a dictionary. Type: {type(response_data)}", style="red")
            raise TypeError("Final response data is not a dictionary and cannot be serialized.")

        console.print(f"[bold red]--- Final Return Data Type ---[/bold red]")
        console.print(f"[red]Returning data type:[/red] {type(response_data)}")
        console.print(f"[red]Returning data value starts with:[/red] {str(response_data)[:100]}...")

        return JSONResponse(content=response_data)

    except Exception as e:
        console.print(f"❌ API Call Failed: {e}", style="red")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/run_crew_ai_form/")
async def run_crew_ai_form(
        topic: str = Form(...),
        category: str = Form("general"),
        context: Optional[str] = Form(""),
        research_focus: Optional[str] = Form(""),
        ai_provider: Optional[str] = Form(None),
        server_endpoint: Optional[str] = Form(None),
        model_name: Optional[str] = Form(None),
        files: List[UploadFile] = File([])
):
    """
    Endpoint to trigger a self-hosted CrewAI crew using multipart/form-data.
    """
    temp_dir = Path("/tmp/uploads_form")
    temp_dir.mkdir(exist_ok=True)
    uploaded_files_paths = []

    try:
        inputs = {
            'topic': topic,
            'category': category,
            'context': context,
            'research_focus': research_focus,
            'ai_provider': ai_provider,
            'server_endpoint': server_endpoint,
            'model_name': model_name,
        }

        for file in files:
            file_location = temp_dir / file.filename
            with open(file_location, "wb") as buffer:
                shutil.copyfileobj(file.file, buffer)
            uploaded_files_paths.append(str(file_location))

        response = process_crew_request(inputs, uploaded_files_paths)
        return response
    finally:
        for file_path in uploaded_files_paths:
            Path(file_path).unlink(missing_ok=True)
        if temp_dir.exists():
            shutil.rmtree(temp_dir, ignore_errors=True)


@app.post("/run_crew_ai_json/")
def run_crew_ai_json(
        request: CrewRequest,
        router: DistributedRouter = Depends(get_distributed_router)
):
    """
    Endpoint to trigger a self-hosted CrewAI crew using a JSON payload with Base64 files.
    """
    temp_dir = Path("/tmp/uploads_json")
    temp_dir.mkdir(exist_ok=True)
    uploaded_files_paths = []

    try:
        inputs = request.inputs

        if inputs.get('files'):
            for file_info in inputs['files']:
                file_name = file_info['name']
                base64_data = file_info['base64_data']

                file_bytes = base64.b64decode(base64_data)
                file_location = temp_dir / file_name
                with open(file_location, "wb") as buffer:
                    buffer.write(file_bytes)
                uploaded_files_paths.append(str(file_location))

        response = process_crew_request(inputs, uploaded_files_paths)
        return response
    except Exception as e:
        console.print(f"❌ API Call Failed: {e}", style="red")
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        for file_path in uploaded_files_paths:
            Path(file_path).unlink(missing_ok=True)
        if temp_dir.exists():
            shutil.rmtree(temp_dir, ignore_errors=True)


if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=3939, reload=True)
