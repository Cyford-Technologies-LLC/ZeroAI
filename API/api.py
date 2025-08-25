# /opt/ZeroAI/API/api.py

import uvicorn
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Dict, Any

# Import your self-hosted crew components
# Assuming ai_crew.py and cache_manager.py are in a directory accessible from here
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
    Endpoint to trigger a self-hosted CrewAI crew using AICrewManager.
    """
    try:
        topic = request.inputs.get("topic")
        if not topic:
            raise ValueError("Missing required 'topic' input.")

        # Initialize the AI Crew Manager with the topic from the request
        manager = AICrewManager(task=topic)

        # Create a research crew
        crew = manager.create_research_crew()

        # Check cache first
        cached_result = cache.get(topic, "crew_research")
        if cached_result:
            result = cached_result
        else:
            # Execute the crew
            result = manager.execute_crew(crew, {"topic": topic})
            # Cache the result
            cache.set(topic, "crew_research", str(result))

        return {"result": str(result)}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
