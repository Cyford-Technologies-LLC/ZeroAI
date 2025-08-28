import uvicorn
from fastapi import FastAPI, HTTPException, Depends, Form, UploadFile, File
from pydantic import BaseModel
from typing import Dict, Any, List, Optional
from pathlib import Path
from rich.console import Console
import sys
import shutil
import base64
import os
from crewai import CrewOutput, TaskOutput # Import necessary classes
from crewai.telemetry.telemetry_metrics import UsageMetrics # Correct import for UsageMetrics

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

class FileData(BaseModel):
    name: str
    type: str
    base64_data: str

class CrewRequest(BaseModel):
    inputs: Dict[str, Any]

def get_distributed_router():
    return distributed_router

def crew_output_to_dict(crew_output: CrewOutput) -> Dict[str, Any]:
    """Converts a CrewOutput object to a dictionary for JSON serialization."""
    if not isinstance(crew_output, CrewOutput):
        return crew_output

    tasks_output = [task_output_to_dict(task) for task in crew_output.tasks_output]
    token_usage_dict = usage_metrics_to_dict(crew_output.token_usage)

    return {
        "raw": crew_output.raw,
        "pydantic": crew_output.pydantic,
        "json_dict": crew_output.json_dict,
        "tasks_output": tasks_output,
        "token_usage": token_usage_dict
    }

def task_output_to_dict(task_output: TaskOutput) -> Dict[str, Any]:
    """Converts a TaskOutput object to a dictionary for JSON serialization."""
    return {
        "description": task_output.description,
        "name": task_output.name,
        "expected_output": task_output.expected_output,
        "summary": task_output.summary,
        "raw": task_output.raw,
        "pydantic": task_output.pydantic,
        "json_dict": task_output.json_dict,
        "agent": task_output.agent,
        "output_format": task_output.output_format.name if task_output.output_format else None
    }

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
    Handles the core logic for running the AI crew, with added capability to read
    uploaded file content and include it in the inputs.
    """
    try:
        topic = inputs.get("topic")
        category = inputs.get("category", "general")

        if not topic:
            raise ValueError("Missing required 'topic' input.")

        # Read content of the first uploaded file and add to inputs
        inputs['file_content'] = ""
        if uploaded_files_paths:
            try:
                # Assuming only one file for simplicity in this example
                with open(uploaded_files_paths, 'r') as f:
                    inputs['file_content'] = f.read()
            except Exception as e:
                console.print(f"❌ Error reading file: {e}", style="red")
                inputs['file_content'] = "Error reading uploaded file."

        inputs['files'] = uploaded_files_paths # Store file paths for potential other uses

        console.print(f"✅ Received API Request:", style="green")
        console.print(f"   Topic: {topic}")
        console.print(f"   Category: {category}")
        console.print(f"   AI Provider: {inputs.get('ai_provider')}")
        console.print(f"   Model Name: {inputs.get('model_name')}")
        console.print(f"   Uploaded Files: {[os.path.basename(f) for f in uploaded_files_paths]}")

        # The AICrewManager is initialized with all inputs AND the category
        manager = AICrewManager(distributed_router, inputs=inputs, category=category)

        # Corrected call to pass the inputs dictionary
        crew = manager.create_crew_for_category(inputs)

        cache_key = f"{category}_{topic}_{inputs.get('context', '')}_{inputs.get('research_focus', '')}_{inputs.get('ai_provider', '')}_{inputs.get('model_name', '')}"
        cached_response = cache.get(cache_key, "crew_result")

        if cached_response:
            response_data = cached_response
        else:
            crew_output = manager.execute_crew(crew, inputs)
            # Convert CrewOutput object to a serializable dictionary
            response_data = crew_output_to_dict(crew_output)
            cache.set(cache_key, "crew_result", response_data)

        return response_data
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

        response_data = process_crew_request(inputs, uploaded_files_paths)
        return response_data
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

        response_data = process_crew_request(inputs, uploaded_files_paths)
        return response_data
    except Exception as e:
        console.print(f"❌ API Call Failed: {e}", style="red")
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        for file_path in uploaded_files_paths:
            Path(file_path).unlink(missing_ok=True)
        if temp_dir.exists():
            shutil.rmtree(temp_dir, ignore_errors=True)

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=3939)

