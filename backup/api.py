# /opt/ZeroAI/API/api.py

import uvicorn
from fastapi import FastAPI, HTTPException, Depends # Import Depends
from pydantic import BaseModel
from typing import Dict, Any
from pathlib import Path
from rich.console import Console

# Fix: Adjust sys.path for robust import resolution
import sys
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

# Fix: Import specific components
from peer_discovery import PeerDiscovery # Correct PeerDiscovery import
from distributed_router import DistributedRouter
from ai_crew import AICrewManager
from cache_manager import cache

# Initialize a console for logging
console = Console()

# --- FIX: Initialize complex objects once globally ---
# This is the key change to ensure the router and its components are set up correctly.
peer_discovery_instance = PeerDiscovery()
distributed_router = DistributedRouter(peer_discovery_instance)
# --- END FIX ---

# Initialize FastAPI app
app = FastAPI(
    title="CrewAI Endpoint API",
    description="API to expose CrewAI crews as endpoints.",
    version="1.0.0",
)

# Define a Pydantic model for request validation
class CrewRequest(BaseModel):
    inputs: Dict[str, Any]

# Define a dependency provider for the router
def get_distributed_router():
    return distributed_router

@app.post("/run_crew_ai/")
def run_crew_ai(
    request: CrewRequest,
    router: DistributedRouter = Depends(get_distributed_router) # Inject the router here
):
    """
    Endpoint to trigger a self-hosted CrewAI crew using AICrewManager.
    """
    try:
        inputs = request.inputs
        topic = inputs.get("topic")
        category = inputs.get("category", "general")

        if not topic:
            raise ValueError("Missing required 'topic' input.")

        # Fix: Pass the injected router instance to the AICrewManager constructor
        manager = AICrewManager(router, inputs=inputs)

        # The rest of your code remains largely the same
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
        console.print(f"❌ API Call Failed: {e}", style="red")
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
