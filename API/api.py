# src/learning/frontend_integration.py

import time
import uuid
import json
import logging
from pathlib import Path

# Import the feedback loop
try:
    from learning.feedback_loop import feedback_loop, record_task_result
    has_feedback_loop = True
except ImportError:
    has_feedback_loop = False

logger = logging.getLogger(__name__)

class FrontendLearningAdapter:
    """
    Adapter class to integrate frontend applications with the ZeroAI learning system.
    This provides simplified methods for frontend applications to participate in the
    learning feedback loop.
    """

    @staticmethod
    def start_frontend_task(task_id=None, prompt=None, category="frontend"):
        """
        Start tracking a frontend AI task.

        Args:
            task_id: Optional unique ID for the task (generated if None)
            prompt: The user prompt or query
            category: Task category (default: "frontend")

        Returns:
            dict: Task metadata including task_id and start_time
        """
        if not task_id:
            task_id = str(uuid.uuid4())

        task_meta = {
            "task_id": task_id,
            "prompt": prompt,
            "category": category,
            "start_time": time.time()
        }

        logger.info(f"Started frontend task: {task_id}")
        return task_meta

    @staticmethod
    def complete_frontend_task(task_meta, model_used, success, response_data=None, error_message=None):
        """
        Complete tracking a frontend AI task.

        Args:
            task_meta: Task metadata from start_frontend_task
            model_used: The model used for the task
            success: Whether the task was successful
            response_data: Additional data about the response
            error_message: Error message if task failed

        Returns:
            bool: Whether recording was successful
        """
        if not has_feedback_loop:
            logger.warning("Learning module not available")
            return False

        try:
            # Extract token usage if available
            token_usage = None
            if response_data and isinstance(response_data, dict) and "token_usage" in response_data:
                token_usage = response_data["token_usage"]

            # Record the task result
            result = record_task_result(
                task_id=task_meta.get("task_id", str(uuid.uuid4())),
                prompt=task_meta.get("prompt", ""),
                category=task_meta.get("category", "frontend"),
                model_used=model_used,
                peer_used="frontend",
                start_time=task_meta.get("start_time", time.time() - 1),
                end_time=time.time(),
                success=success,
                error_message=error_message,
                git_changes=None,
                token_usage=token_usage
            )

            logger.info(f"Completed frontend task: {task_meta.get('task_id')} (success: {success})")
            return result
        except Exception as e:
            logger.error(f"Error completing frontend task: {e}")
            return False

    @staticmethod
    def get_preferred_frontend_model(category="frontend"):
        """
        Get preferred model for a category.

        Args:
            category: Task category

        Returns:
            str or None: Preferred model name or None
        """
        if not has_feedback_loop:
            logger.warning("Learning module not available")
            return None

        try:
            # Get preferred model for category
            preferred_model = feedback_loop.get_model_preference(category)

            # If no preference found for this category, try getting general preference
            if not preferred_model:
                preferred_model = feedback_loop.get_model_preference()

            return preferred_model
        except Exception as e:
            logger.error(f"Error getting preferred frontend model: {e}")
            return None

    @staticmethod
    def get_model_preferences_for_frontend(category="frontend"):
        """
        Get all model preferences for a category as ordered list.

        Args:
            category: Task category

        Returns:
            list: List of model names in preference order
        """
        if not has_feedback_loop:
            logger.warning("Learning module not available")
            return []

        try:
            # Try to get category-specific model ranking
            with open(Path("knowledge/learning/learning_metrics.json"), 'r') as f:
                metrics = json.load(f)

            # If we have data for this category
            if category in metrics["categories"] and metrics["categories"][category]["models"]:
                # Sort models by usage count for this category
                category_models = metrics["categories"][category]["models"]
                ranked_models = sorted(category_models.items(), key=lambda x: x[1], reverse=True)
                return [model for model, _ in ranked_models]

            # Fall back to overall ranking
            ranked_models = feedback_loop.get_preference_ranking("models")
            return [model for model, _ in ranked_models]
        except Exception as e:
            logger.error(f"Error getting model preferences for frontend: {e}")
            return []