# src/learning/feedback_loop.py

import json
import time
import os
from pathlib import Path
from typing import Dict, Any, Optional, List
from rich.console import Console

console = Console()

class FeedbackLoop:
    """
    Feedback loop system for learning from AI interactions.
    Tracks model performance, task success rates, and helps optimize model selection.
    """

    def __init__(self):
        """Initialize the feedback loop system."""
        self.metrics_file = Path("knowledge/learning/learning_metrics.json")
        self.outcomes_file = Path("knowledge/learning/task_outcomes.json")

        # Ensure directory exists
        self.metrics_file.parent.mkdir(parents=True, exist_ok=True)

        # Initialize or load metrics
        self._load_or_initialize_metrics()
        self._load_or_initialize_outcomes()

    def _load_or_initialize_metrics(self) -> None:
        """Load existing metrics or initialize new ones."""
        if self.metrics_file.exists():
            try:
                with open(self.metrics_file, 'r') as f:
                    self.metrics = json.load(f)
            except json.JSONDecodeError:
                console.print("⚠️ Error reading metrics file, initializing new metrics", style="yellow")
                self._initialize_empty_metrics()
        else:
            self._initialize_empty_metrics()

    def _initialize_empty_metrics(self) -> None:
        """Initialize empty metrics structure."""
        self.metrics = {
            "models": {},
            "peers": {},
            "categories": {},
            "tokens": {}
        }
        # Save the initialized structure
        self._save_metrics()

    def _load_or_initialize_outcomes(self) -> None:
        """Load existing task outcomes or initialize new ones."""
        if self.outcomes_file.exists():
            try:
                with open(self.outcomes_file, 'r') as f:
                    self.outcomes = json.load(f)
            except json.JSONDecodeError:
                console.print("⚠️ Error reading outcomes file, initializing new outcomes", style="yellow")
                self.outcomes = []
        else:
            self.outcomes = []
            # Save the initialized structure
            self._save_outcomes()

    def _save_metrics(self) -> None:
        """Save metrics to disk."""
        try:
            with open(self.metrics_file, 'w') as f:
                json.dump(self.metrics, f, indent=2)
        except Exception as e:
            console.print(f"⚠️ Error saving metrics: {e}", style="yellow")

    def _save_outcomes(self) -> None:
        """Save task outcomes to disk."""
        try:
            with open(self.outcomes_file, 'w') as f:
                json.dump(self.outcomes, f, indent=2)
        except Exception as e:
            console.print(f"⚠️ Error saving outcomes: {e}", style="yellow")

    def _ensure_model_in_metrics(self, model: str) -> None:
        """Ensure the model exists in metrics."""
        if model not in self.metrics["models"]:
            self.metrics["models"][model] = {
                "tasks": 0,
                "successes": 0,
                "failures": 0,
                "success_rate": 0.0,
                "total_tokens": 0,
                "avg_tokens": 0,
                "total_time": 0,
                "avg_time": 0,
                "tokens": 0  # Learning tokens
            }

    def _ensure_peer_in_metrics(self, peer: str) -> None:
        """Ensure the peer exists in metrics."""
        if peer not in self.metrics["peers"]:
            self.metrics["peers"][peer] = {
                "tasks": 0,
                "successes": 0,
                "failures": 0,
                "tokens": 0  # Learning tokens
            }

    def _ensure_category_in_metrics(self, category: str) -> None:
        """Ensure the category exists in metrics."""
        if category not in self.metrics["categories"]:
            self.metrics["categories"][category] = {
                "tasks": 0,
                "successes": 0,
                "failures": 0,
                "tokens": 0,  # Learning tokens
                "models": {}  # Track which models are used for this category
            }

    def get_model_preference(self, category: str) -> Optional[str]:
        """
        Get the preferred model for a category based on learning.

        Args:
            category: The task category

        Returns:
            The model name or None if no preference
        """
        if category not in self.metrics["categories"]:
            return None

        # Get models used for this category
        models = self.metrics["categories"][category].get("models", {})
        if not models:
            return None

        # Find the model with the most learning tokens
        return max(models.items(), key=lambda x: x[1])[0]

    def get_model_preferences(self, category: str) -> List[str]:
        """
        Get all models for a category, sorted by preference.

        Args:
            category: The task category

        Returns:
            List of model names, ordered by preference
        """
        if category not in self.metrics["categories"]:
            return []

        # Get models used for this category
        models = self.metrics["categories"][category].get("models", {})
        if not models:
            return []

        # Sort models by token count (descending)
        return [model for model, _ in sorted(models.items(), key=lambda x: x[1], reverse=True)]

    def record_task(self, task_data: Dict[str, Any]) -> None:
        """
        Record a task execution result and update metrics.

        Args:
            task_data: Dictionary containing task result data
        """
        try:
            # Extract key information
            task_id = task_data.get("task_id", "unknown")
            model = task_data.get("model_used", "unknown")
            peer = task_data.get("peer_used", "unknown")
            category = task_data.get("category", "general")
            success = task_data.get("success", False)

            # Calculate execution time
            start_time = task_data.get("start_time", 0)
            end_time = task_data.get("end_time", 0)
            execution_time = end_time - start_time

            # Get token usage
            token_usage = task_data.get("token_usage", {})
            total_tokens = token_usage.get("total_tokens", 0) if isinstance(token_usage, dict) else 0

            # Calculate learning tokens
            # Base token: Every task gets at least 1 token
            learning_tokens = 1

            # Success bonus: Successful tasks get extra tokens
            if success:
                learning_tokens += 2

            # Efficiency bonus: Fast tasks get more tokens (relative to category average)
            if category in self.metrics["categories"]:
                category_tasks = self.metrics["categories"][category]["tasks"]
                if category_tasks > 0:
                    # Get the average time for this category if we have tasks
                    # We don't track time per category yet, so use a heuristic
                    # based on successful task count and model stats
                    avg_time_estimate = 10.0  # Default assumption: 10 seconds

                    # Time efficiency bonus
                    if execution_time < avg_time_estimate and execution_time > 0:
                        time_bonus = min(3, int(avg_time_estimate / execution_time))
                        learning_tokens += time_bonus

            # Ensure model, peer, and category exist in metrics
            self._ensure_model_in_metrics(model)
            self._ensure_peer_in_metrics(peer)
            self._ensure_category_in_metrics(category)

            # Update model metrics
            model_stats = self.metrics["models"][model]
            model_stats["tasks"] += 1
            if success:
                model_stats["successes"] += 1
            else:
                model_stats["failures"] += 1
            model_stats["success_rate"] = model_stats["successes"] / model_stats["tasks"]
            model_stats["total_tokens"] += total_tokens
            model_stats["avg_tokens"] = model_stats["total_tokens"] / model_stats["tasks"]
            model_stats["total_time"] += execution_time
            model_stats["avg_time"] = model_stats["total_time"] / model_stats["tasks"]
            model_stats["tokens"] += learning_tokens

            # Update peer metrics
            peer_stats = self.metrics["peers"][peer]
            peer_stats["tasks"] += 1
            if success:
                peer_stats["successes"] += 1
            else:
                peer_stats["failures"] += 1
            peer_stats["tokens"] += learning_tokens

            # Update category metrics
            category_stats = self.metrics["categories"][category]
            category_stats["tasks"] += 1
            if success:
                category_stats["successes"] += 1
            else:
                category_stats["failures"] += 1
            category_stats["tokens"] += learning_tokens

            # Track which models are used for this category
            if "models" not in category_stats:
                category_stats["models"] = {}
            if model not in category_stats["models"]:
                category_stats["models"][model] = 0
            category_stats["models"][model] += learning_tokens

            # Add to outcomes
            self.outcomes.append({
                "task_id": task_id,
                "timestamp": time.time(),
                "date": time.strftime("%Y-%m-%d %H:%M:%S"),
                "model": model,
                "peer": peer,
                "category": category,
                "prompt": task_data.get("prompt", ""),
                "success": success,
                "execution_time": execution_time,
                "tokens": total_tokens,
                "learning_tokens": learning_tokens,
                "error_message": task_data.get("error_message", None) if not success else None
            })

            # Save updated metrics and outcomes
            self._save_metrics()
            self._save_outcomes()

            console.print(f"✅ Recorded task result with {learning_tokens} learning tokens", style="green")

        except Exception as e:
            console.print(f"⚠️ Error recording task result: {e}", style="yellow")


# Create a singleton instance
feedback_loop = FeedbackLoop()

def record_task_result(
    task_id: str,
    prompt: str,
    category: str,
    model_used: str,
    peer_used: str,
    start_time: float,
    end_time: float,
    success: bool,
    error_message: Optional[str] = None,
    git_changes: Optional[Dict[str, Any]] = None,
    token_usage: Optional[Dict[str, int]] = None
) -> bool:
    """
    Record a task execution result.

    Args:
        task_id: Unique identifier for the task
        prompt: The task prompt
        category: Task category
        model_used: The model used for the task
        peer_used: The peer that processed the task
        start_time: Task start timestamp
        end_time: Task end timestamp
        success: Whether the task completed successfully
        error_message: Error message if task failed
        git_changes: Git changes made during task
        token_usage: Token usage statistics

    Returns:
        True if successfully recorded, False otherwise
    """
    try:
        task_data = {
            "task_id": task_id,
            "prompt": prompt,
            "category": category,
            "model_used": model_used,
            "peer_used": peer_used,
            "start_time": start_time,
            "end_time": end_time,
            "success": success,
            "error_message": error_message,
            "git_changes": git_changes,
            "token_usage": token_usage
        }

        feedback_loop.record_task(task_data)
        return True
    except Exception as e:
        console.print(f"⚠️ Error in record_task_result: {e}", style="yellow")
        return False