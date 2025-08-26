# /opt/ZeroAI/API/api.py

import uvicorn
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Dict, Any

# Import your self-hosted crew components
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))
from ai_crew import AICrewManager
from cache_manager import cache

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

        # Initialize the AI Crew Manager
        manager = AICrewManager(**inputs)
        
        # Decide which crew to create
        crew = manager.create_crew_for_category(inputs)

        # Check cache first (consider including max_tokens in the cache key)
        cache_key = f"{category}_{topic}_{inputs.get('max_tokens', '')}_{inputs.get('context', '')}_{inputs.get('research_focus', '')}"
        cached_response = cache.get(cache_key, "crew_result")

        if cached_response:
            # If from cache, it's already a dictionary
            response_data = cached_response
        else:
            # Execute the crew and get the full response dictionary
            response_data = manager.execute_crew(crew, inputs)
            # Cache the full response dictionary
            cache.set(cache_key, "crew_result", response_data)

        return response_data
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
