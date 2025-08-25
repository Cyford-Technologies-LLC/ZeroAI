# /opt/ZeroAI/API/api.py

import uvicorn
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Dict, Any

# This is the ONLY import statement you need for your crew.
from ZeroAI.crew import LatestAiDevelopmentCrew

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
# @app.post("/run_code_crew_ai/")
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

# def run_code_crew_ai(request: CrewRequest):
#     """
#     Endpoint to trigger a specific CrewAI crew.
#     """
#     try:
#         crew_instance = LatestAiDevelopmentCrew().crew()
#         result = crew_instance.kickoff(inputs=request.inputs)
#         return {"result": result}
#     except Exception as e:
#         raise HTTPException(status_code=500, detail=str(e))


if __name__ == "__main__":
    uvicorn.run("API.api:app", host="0.0.0.0", port=3939)
