# /opt/ZeroAI/API/api.py

import uvicorn
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Dict, Any
from pathlib import Path

# Fix: Adjust sys.path for robust import resolution
import sys
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

# Fix: Import specific components and instantiate the router
from peer_discovery import peer_discovery
from distributed_router import DistributedRouter
from ai_crew import AICrewManager
from cache_manager import cache

# Fix: Create the DistributedRouter instance once at the top level
distributed_router = DistributedRouter(peer_discovery)

# Initialize FastAPI app
app = FastAPI(
    title="CrewAI Endpoint API",
    description="API to expose CrewAI crews as endpoints.",
    version="1.0.0",
)

# Define a Pydantic model for request validation
class CrewRequest(BaseModel):
    inputs: Dict[str, Any]

@app.post("/run_crew_ai/")
def run_crew_ai(request: CrewRequest):
    """
    Endpoint to trigger a self-hosted CrewAI crew using AICrewManager, based on a category.
    """
    try:
        inputs = request.inputs
        topic = inputs.get("topic")
        category = inputs.get("category", "general")

        if not topic:
            raise ValueError("Missing required 'topic' input.")

        # Fix: Pass the router instance to the AICrewManager constructor
        manager = AICrewManager(distributed_router, inputs=inputs)

        # Decide which crew to create
        crew = manager.create_crew_for_category(inputs)

        # Check cache first
        cache_key = f"{category}_{topic}_{inputs.get('max_tokens', '')}_{inputs.get('context', '')}_{inputs.get('research_focus', '')}"
        cached_response = cache.get(cache_key, "crew_result")

        if cached_response:
            response_data = cached_response
        else:
            response_data = manager.execute_crew(crew, inputs)
            cache.set(cache_key, "crew_result", response_data)

        return response_data
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
