# /opt/ZeroAI/API/api.py

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

def process_crew_request(inputs: Dict[str, Any], uploaded_files_paths: List[str]):
    try:
        topic = inputs.get("topic")
        category = inputs.get("category", "general")

        if not topic:
            raise ValueError("Missing required 'topic' input.")

        inputs['files'] = uploaded_files_paths

        console.print(f"✅ Received API Request:", style="green")
        console.print(f"   Topic: {topic}")
        console.print(f"   Category: {category}")
        console.print(f"   AI Provider: {inputs.get('ai_provider')}")
        console.print(f"   Model Name: {inputs.get('model_name')}")
        console.print(f"   Uploaded Files: {[os.path.basename(f) for f in uploaded_files_paths]}")

        manager = AICrewManager(distributed_router, inputs=inputs)
        crew = manager.create_crew_for_category(inputs)

        cache_key = f"{category}_{topic}_{inputs.get('context', '')}_{inputs.get('research_focus', '')}_{inputs.get('ai_provider', '')}_{inputs.get('model_name', '')}"
        cached_response = cache.get(cache_key, "crew_result")

        if cached_response:
            response_data = cached_response
        else:
            response_data = manager.execute_crew(crew, inputs)
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

