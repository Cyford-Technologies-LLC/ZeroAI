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
        topic = request.inputs.get("topic")
        category = request.inputs.get("category", "general")
        context = request.inputs.get("context", "")
        research_focus = request.inputs.get("research_focus", "")

        if not topic:
            raise ValueError("Missing required 'topic' input.")

        # Initialize the AI Crew Manager
        manager = AICrewManager(
            task=topic,
            category=category,
            context=context,
            research_focus=research_focus
        )

        # Get LLM details immediately after manager is initialized
        llm_details = manager.get_llm_details()

        # Decide which crew to create
        crew = manager.create_crew_for_category(request.inputs)

        # Check cache first
        cache_key = f"{category}_{topic}_{context}_{research_focus}"
        cached_result = cache.get(cache_key, "crew_result")

        if cached_result:
            result = cached_result
        else:
            result = manager.execute_crew(crew, request.inputs)
            cache.set(cache_key, "crew_result", str(result))

        return {
            "result": str(result),
            "llm_details": llm_details  # Include LLM details in the response
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
