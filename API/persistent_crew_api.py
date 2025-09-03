from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Dict, Any
import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from src.utils.persistent_crew import crew_pool

app = FastAPI(title="ZeroAI Persistent Crew API")

class TaskRequest(BaseModel):
    project_id: str
    prompt: str
    category: str = "general"
    repository: str = None

class TaskResponse(BaseModel):
    task_id: str
    status: str
    message: str

@app.post("/add_task/", response_model=TaskResponse)
async def add_task(request: TaskRequest):
    """Add task to persistent crew queue."""
    try:
        task_inputs = {
            "prompt": request.prompt,
            "category": request.category,
            "repository": request.repository
        }
        
        task_id = crew_pool.add_task(request.project_id, task_inputs)
        
        return TaskResponse(
            task_id=task_id,
            status="queued",
            message=f"Task queued for {request.project_id}"
        )
        
    except ValueError as e:
        raise HTTPException(status_code=404, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/status/")
async def get_status():
    """Get status of all persistent crews."""
    return crew_pool.status_all()

@app.get("/status/{project_id}")
async def get_project_status(project_id: str):
    """Get status of specific project crew."""
    crew = crew_pool.get_crew(project_id)
    if not crew:
        raise HTTPException(status_code=404, detail=f"No crew found for {project_id}")
    return crew.status()

@app.get("/health/")
async def health_check():
    """Health check endpoint."""
    return {"status": "healthy", "crews": len(crew_pool.crews)}