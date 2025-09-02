# src/learning/frontend_integration.py

from typing import Dict, Any, Optional, List
import time
import json
from rich.console import Console
from .feedback_loop import feedback_loop, record_task_result

console = Console()

class FrontendLearningAdapter:
    """
    Adapter class to integrate learning system with frontend applications.
    Provides simplified methods for recording frontend AI interactions.
    """

    @staticmethod
    def start_frontend_task(task_id: str, prompt: str, category: str = "frontend") -> Dict[str, Any]:
        """
        Start tracking a frontend AI task.

        Args:
            task_id: Unique identifier for the task
            prompt: The user prompt or query
            category: Task category (default: "frontend")

        Returns:
            Dict with task metadata including start_time
        """
        return {
            "task_id": task_id,
            "prompt": prompt,
            "category": category,
            "start_time": time.time()
        }

    @staticmethod
    def complete_frontend_task(
        task_meta: Dict[str, Any],
        model_used: str,
        success: bool,
        response_data: Optional[Dict[str, Any]] = None,
        error_message: Optional[str] = None
    ) -> bool:
        """
        Complete tracking of a frontend AI task.

        Args:
            task_meta: The metadata dict returned by start_frontend_task
            model_used: The model used (e.g., "gpt-4", "llama3.2:latest")
            success: Whether the interaction was successful
            response_data: Additional data about the response
            error_message: Error message if interaction failed

        Returns:
            True if successfully recorded
        """
        end_time = time.time()

        # Extract token usage from response data if available
        token_usage = None
        if response_data and "token_usage" in response_data:
            token_usage = response_data["token_usage"]

        # Always use "frontend" as the peer for frontend tasks
        peer_used = "frontend"

        return record_task_result(
            task_id=task_meta["task_id"],
            prompt=task_meta["prompt"],
            category=task_meta["category"],
            model_used=model_used,
            peer_used=peer_used,
            start_time=task_meta["start_time"],
            end_time=end_time,
            success=success,
            error_message=error_message,
            git_changes=None,  # Frontend tasks don't have git changes
            token_usage=token_usage
        )

    @staticmethod
    def get_preferred_frontend_model(category: str = "frontend") -> Optional[str]:
        """
        Get the preferred model for frontend tasks in a category.

        Args:
            category: The task category (default: "frontend")

        Returns:
            The preferred model name or None
        """
        return feedback_loop.get_model_preference(category)

    @staticmethod
    def get_model_preferences_for_frontend(category: str = "frontend") -> List[str]:
        """
        Get all models for frontend tasks, sorted by preference.

        Args:
            category: The task category (default: "frontend")

        Returns:
            List of model names, ordered by preference
        """
        return feedback_loop.get_model_preferences(category)


# Create an API that can be used in JavaScript via a REST endpoint
def create_frontend_api_handlers():
    """
    Create API route handlers for frontend integration.
    Returns functions that can be used with a web framework like Flask.
    """

    def start_task_handler(request_data):
        """Handle start-task requests from frontend."""
        try:
            task_id = request_data.get("task_id")
            prompt = request_data.get("prompt")
            category = request_data.get("category", "frontend")

            if not task_id or not prompt:
                return {"error": "Missing required fields"}, 400

            result = FrontendLearningAdapter.start_frontend_task(task_id, prompt, category)
            return {"success": True, "task_meta": result}, 200
        except Exception as e:
            return {"error": str(e)}, 500

    def complete_task_handler(request_data):
        """Handle complete-task requests from frontend."""
        try:
            task_meta = request_data.get("task_meta")
            model_used = request_data.get("model_used", "unknown")
            success = request_data.get("success", False)
            response_data = request_data.get("response_data")
            error_message = request_data.get("error_message")

            if not task_meta:
                return {"error": "Missing task_meta"}, 400

            result = FrontendLearningAdapter.complete_frontend_task(
                task_meta, model_used, success, response_data, error_message
            )
            return {"success": result}, 200
        except Exception as e:
            return {"error": str(e)}, 500

    def get_model_preference_handler(request_data):
        """Handle get-model-preference requests from frontend."""
        try:
            category = request_data.get("category", "frontend")
            preferred_model = FrontendLearningAdapter.get_preferred_frontend_model(category)
            all_preferences = FrontendLearningAdapter.get_model_preferences_for_frontend(category)

            return {
                "success": True,
                "preferred_model": preferred_model,
                "all_preferences": all_preferences
            }, 200
        except Exception as e:
            return {"error": str(e)}, 500

    return {
        "start_task": start_task_handler,
        "complete_task": complete_task_handler,
        "get_model_preference": get_model_preference_handler
    }