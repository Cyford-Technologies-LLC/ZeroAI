# API/api.py

import os
import sys
import uvicorn
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Dict, Any

# Fix the import to be a direct import, not relative
from ZeroAI.crew import LatestAiDevelopmentCrew


# Get the absolute path to the project root and add `src` to the Python path
project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
sys.path.append(os.path.join(project_root, 'src'))

# Import your CrewAI module from the new path
try:
    from ZeroAI.crew import LatestAiDevelopmentCrew
except ImportError as e:
    raise RuntimeError(
        "Could not import your CrewAI crew. "
        "Check your project structure and import path."
    ) from e

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
    Endpoint to trigger a specific CrewAI crew.
    """
    try:
        crew_instance = LatestAiDevelopmentCrew().crew()
        result = crew_instance.kickoff(inputs=request.inputs)
        return {"result": result}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    uvicorn.run("API.api:app", host="0.0.0.0", port=3939)
