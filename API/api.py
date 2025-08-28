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
    using Pydantic V2's model_dump() method.
    """
    if not isinstance(crew_output, CrewOutput):
        return crew_output

    data = crew_output.model_dump()
    if hasattr(crew_output, 'result'):
        data['result'] = crew_output.result
    return data


def task_output_to_dict(task_output: TaskOutput) -> Dict[str, Any]:
    """Converts a TaskOutput object to a dictionary for JSON serialization."""
    # Use Pydantic V2's recommended model_dump() method
    return task_output.model_dump()


def usage_metrics_to_dict(usage_metrics: UsageMetrics) -> Dict[str, Any]:
    """Converts a UsageMetrics object to a dictionary for JSON serialization."""
    return {
        "total_tokens": usage_metrics.total_tokens,
        "prompt_tokens": usage_metrics.prompt_tokens,
        "completion_tokens": usage_metrics.completion_tokens,
        "successful_requests": usage_metrics.successful_requests
    }


def handle_crew_result(crew_result: Any, cache_key: str):
    """
    Standardizes the handling of AI crew results, ensuring a JSON-serializable
    dictionary is always returned and cached.
    """
    if isinstance(crew_result, CrewOutput):
        console.print(f"🔄 Converting CrewOutput to dictionary for serialization.", style="yellow")

        # --- DEBUGGING STEP ---
        console.print("[bold cyan]--- CrewOutput Object Dump ---[/bold cyan]")
        console.print(crew_result)  # Print the full object
        console.print("[bold cyan]----------------------------[/bold cyan]")
        # --- END DEBUGGING STEP ---

        response_data = crew_output_to_dict(crew_result)
        cache.set(cache_key, "crew_result", response_data)
        return response_data
    elif isinstance(crew_result, dict):
        console.print(f"✅ Cached data is already a dictionary.", style="blue")
        return crew_result
    else:
        # Fallback for unexpected data types
        console.print(f"❌ Unexpected data type from cache: {type(crew_result)}", style="red")
        raise TypeError(f"Cannot serialize object of type {type(crew_result)}")


def process_crew_request(inputs: Dict[str, Any], uploaded_files_paths: List[str], output_format: str):
    """
    Handles the core logic for running the AI crew and returns output based on format.
    """
    try:
        topic = inputs.get("topic")
        # Ensure 'auto' is the default category if not provided
        category = inputs.get("category", "auto")

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
            response_data = handle_crew_result(cached_response, cache_key)
        else:
            console.print(f"⚠️ Cache Miss. Executing AI Crew...", style="yellow")
            crew_output = crew.kickoff()
            response_data = handle_crew_result(crew_output, cache_key)

        if output_format == "json":
            return {"result": response_data.get("result", "No result available.")}
        elif output_format == "text":
            return {"result": response_data.get("result", "No result available.")}
        else:
            return response_data

    except Exception as e:
        console.print(f"❌ API Call Failed: {e}", style="red")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/run_crew_ai_json/")
async def run_crew_ai_json(crew_request: CrewRequest,
                           distributed_router_dep: PeerDiscovery = Depends(get_distributed_router)):
    """Run a specific AI crew with inputs provided in a JSON payload."""
    return process_crew_request(crew_request.inputs, [], "json")


@app.post("/run_crew_ai_text/")
async def run_crew_ai_text(crew_request: CrewRequest,
                           distributed_router_dep: PeerDiscovery = Depends(get_distributed_router)):
    """Run a specific AI crew with inputs provided in a JSON payload and return text output."""
    return process_crew_request(crew_request.inputs, [], "text")


@app.post("/run_crew_ai_multipart/")
async def run_crew_ai_multipart(
        inputs: str = Form(...),
        files: Optional[List[UploadFile]] = File(None),
        distributed_router_dep: PeerDiscovery = Depends(get_distributed_router)
):
    """Run a specific AI crew with inputs and file uploads from a multipart/form-data payload."""
    inputs_dict = json.loads(inputs)
    uploaded_files_paths = []
    temp_dir = Path("uploads")
    temp_dir.mkdir(exist_ok=True)

    if files:
        for file in files:
            file_path = temp_dir / file.filename
            with file_path.open("wb") as buffer:
                shutil.copyfileobj(file.file, buffer)
            uploaded_files_paths.append(str(file_path))

    try:
        response = process_crew_request(inputs_dict, uploaded_files_paths, "json")
    finally:
        # Clean up uploaded files
        for file_path in uploaded_files_paths:
            os.remove(file_path)

    return response


if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
