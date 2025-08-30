# src/api/learning_api.py

from fastapi import APIRouter, HTTPException, Request
from pydantic import BaseModel
from typing import Dict, Any, Optional, List
import json
import time
import uuid

# Import the learning feedback loop
try:
    from learning.feedback_loop import feedback_loop, record_task_result
    from learning.frontend_integration import FrontendLearningAdapter
    has_learning = True
except ImportError:
    has_learning = False
    # Create dummy record_task_result function
    def record_task_result(*args, **kwargs):
        return True

    # Create dummy FrontendLearningAdapter class
    class FrontendLearningAdapter:
        @staticmethod
        def start_frontend_task(*args, **kwargs):
            return {"task_id": "dummy", "start_time": time.time()}

        @staticmethod
        def complete_frontend_task(*args, **kwargs):
            return True

        @staticmethod
        def get_preferred_frontend_model(*args, **kwargs):
            return None

        @staticmethod
        def get_model_preferences_for_frontend(*args, **kwargs):
            return []

# Define API models
class StartTaskRequest(BaseModel):
    task_id: Optional[str] = None
    prompt: str
    category: Optional[str] = "frontend"

class CompleteTaskRequest(BaseModel):
    task_meta: Dict[str, Any]
    model_used: str
    success: bool
    response_data: Optional[Dict[str, Any]] = None
    error_message: Optional[str] = None

class ModelPreferenceRequest(BaseModel):
    category: Optional[str] = "frontend"

# Create API router
router = APIRouter(prefix="/api/learning", tags=["learning"])

@router.post("/start-task")
async def start_task(request: StartTaskRequest):
    """Start tracking a frontend AI task."""
    if not has_learning:
        return {"success": False, "error": "Learning module not available"}

    try:
        task_id = request.task_id or str(uuid.uuid4())
        task_meta = FrontendLearningAdapter.start_frontend_task(
            task_id=task_id,
            prompt=request.prompt,
            category=request.category
        )

        return {"success": True, "task_meta": task_meta}
    except Exception as e:
        return {"success": False, "error": str(e)}

@router.post("/complete-task")
async def complete_task(request: CompleteTaskRequest):
    """Complete tracking a frontend AI task."""
    if not has_learning:
        return {"success": False, "error": "Learning module not available"}

    try:
        result = FrontendLearningAdapter.complete_frontend_task(
            task_meta=request.task_meta,
            model_used=request.model_used,
            success=request.success,
            response_data=request.response_data,
            error_message=request.error_message
        )

        return {"success": result}
    except Exception as e:
        return {"success": False, "error": str(e)}

@router.post("/get-model-preference")
async def get_model_preference(request: ModelPreferenceRequest):
    """Get model preferences for a category."""
    if not has_learning:
        return {
            "success": True,
            "preferred_model": None,
            "all_preferences": []
        }

    try:
        preferred_model = FrontendLearningAdapter.get_preferred_frontend_model(request.category)
        all_preferences = FrontendLearningAdapter.get_model_preferences_for_frontend(request.category)

        return {
            "success": True,
            "preferred_model": preferred_model,
            "all_preferences": all_preferences
        }
    except Exception as e:
        return {"success": False, "error": str(e)}