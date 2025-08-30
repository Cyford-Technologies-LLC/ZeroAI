# src/learning/feedback_loop.py
import json
import time
from pathlib import Path
import logging

logger = logging.getLogger(__name__)

class FeedbackLoop:
    """Token-based learning system for the ZeroAI framework."""
    
    def __init__(self, storage_dir="knowledge/learning"):
        self.storage_dir = Path(storage_dir)
        self.storage_dir.mkdir(parents=True, exist_ok=True)
        
        # Initialize outcome and metrics files
        self.outcomes_file = self.storage_dir / "task_outcomes.json"
        self.metrics_file = self.storage_dir / "learning_metrics.json"
        
        # Create files if they don't exist
        if not self.outcomes_file.exists():
            with open(self.outcomes_file, 'w') as f:
                json.dump([], f)
        
        if not self.metrics_file.exists():
            with open(self.metrics_file, 'w') as f:
                json.dump({
                    "models": {},
                    "peers": {},
                    "categories": {},
                    "tokens": {}
                }, f)
    
    def record_task(self, task_id, prompt, category, model_used, peer_used, 
                   start_time, end_time, success, error_message=None, 
                   git_changes=None, token_usage=None):
        """Record a task outcome in the feedback loop."""
        try:
            # Create task outcome record
            outcome = {
                "task_id": task_id,
                "prompt": prompt,
                "category": category,
                "model_used": model_used,
                "peer_used": peer_used,
                "start_time": start_time,
                "end_time": end_time,
                "execution_time": end_time - start_time,
                "success": success,
                "error_message": error_message,
                "git_changes": git_changes,
                "token_usage": token_usage,
                "recorded_at": time.time()
            }
            
            # Save the outcome
            self._add_outcome(outcome)
            
            # Update metrics based on outcome
            self._update_metrics(outcome)
            
            logger.info(f"Recorded task outcome for task ID {task_id}")
            return True
        except Exception as e:
            logger.error(f"Error recording task outcome: {e}")
            return False
    
    def _add_outcome(self, outcome):
        """Add a task outcome to the outcomes file."""
        try:
            with open(self.outcomes_file, 'r') as f:
                outcomes = json.load(f)
            
            outcomes.append(outcome)
            
            with open(self.outcomes_file, 'w') as f:
                json.dump(outcomes, f, indent=2)
            
            return True
        except Exception as e:
            logger.error(f"Error recording outcome: {e}")
            return False
    
    def _update_metrics(self, outcome):
        """Update learning metrics based on task outcome."""
        try:
            with open(self.metrics_file, 'r') as f:
                metrics = json.load(f)
            
            # Update model metrics
            model = outcome["model_used"]
            if model not in metrics["models"]:
                metrics["models"][model] = {
                    "tasks": 0,
                    "successes": 0,
                    "failures": 0,
                    "total_tokens": 0,
                    "total_time": 0,
                    "success_rate": 0,
                    "avg_tokens": 0,
                    "avg_time": 0,
                    "tokens": 0  # learning tokens
                }
            
            metrics["models"][model]["tasks"] += 1
            if outcome["success"]:
                metrics["models"][model]["successes"] += 1
                # Add tokens for successful execution
                metrics["models"][model]["tokens"] += 5
            else:
                metrics["models"][model]["failures"] += 1
            
            metrics["models"][model]["total_time"] += outcome["execution_time"]
            
            if outcome["token_usage"] and isinstance(outcome["token_usage"], dict) and "total_tokens" in outcome["token_usage"]:
                metrics["models"][model]["total_tokens"] += outcome["token_usage"]["total_tokens"]
            
            # Calculate averages and rates
            metrics["models"][model]["success_rate"] = metrics["models"][model]["successes"] / metrics["models"][model]["tasks"]
            metrics["models"][model]["avg_time"] = metrics["models"][model]["total_time"] / metrics["models"][model]["tasks"]
            
            if metrics["models"][model]["total_tokens"] > 0:
                metrics["models"][model]["avg_tokens"] = metrics["models"][model]["total_tokens"] / metrics["models"][model]["tasks"]
            
            # Update category metrics
            category = outcome["category"]
            if category not in metrics["categories"]:
                metrics["categories"][category] = {
                    "models": {},
                    "tasks": 0,
                    "successes": 0,
                    "tokens": 0
                }
            
            metrics["categories"][category]["tasks"] += 1
            if outcome["success"]:
                metrics["categories"][category]["successes"] += 1
                metrics["categories"][category]["tokens"] += 3
            
            # Track which models are used for this category
            if model not in metrics["categories"][category]["models"]:
                metrics["categories"][category]["models"][model] = 0
            
            metrics["categories"][category]["models"][model] += 1
            
            # Update peer metrics
            peer = outcome["peer_used"]
            if peer not in metrics["peers"]:
                metrics["peers"][peer] = {
                    "tasks": 0,
                    "successes": 0,
                    "tokens": 0
                }
            
            metrics["peers"][peer]["tasks"] += 1
            if outcome["success"]:
                metrics["peers"][peer]["successes"] += 1
                metrics["peers"][peer]["tokens"] += 2
            
            # Save updated metrics
            with open(self.metrics_file, 'w') as f:
                json.dump(metrics, f, indent=2)
            
            return True
        except Exception as e:
            logger.error(f"Error updating metrics: {e}")
            return False

    def get_model_preference(self, category=None):
        """Get model preferences based on accumulated tokens."""
        try:
            with open(self.metrics_file, 'r') as f:
                metrics = json.load(f)
            
            # If category is specified, use category-specific model preferences
            if category and category in metrics["categories"]:
                category_models = metrics["categories"][category]["models"]
                if not category_models:
                    return self._get_overall_model_preference(metrics)
                    
                # Return the most used model for this category
                return max(category_models.items(), key=lambda x: x[1])[0]
            else:
                return self._get_overall_model_preference(metrics)
                
        except Exception as e:
            logger.error(f"Error getting model preference: {e}")
            return None
    
    def _get_overall_model_preference(self, metrics):
        """Get overall model preference based on tokens."""
        if not metrics["models"]:
            return None
            
        # Return the model with the most tokens
        return max(metrics["models"].items(), key=lambda x: x[1]["tokens"])[0]
    
    def get_preference_ranking(self, entity_type="models"):
        """Get ranked preferences for models, peers, or categories."""
        try:
            with open(self.metrics_file, 'r') as f:
                metrics = json.load(f)
            
            if entity_type not in ["models", "peers", "categories"]:
                return []
                
            entities = metrics[entity_type]
            
            if entity_type == "models":
                # Sort models by tokens
                return sorted(entities.items(), key=lambda x: x[1]["tokens"], reverse=True)
            elif entity_type == "peers":
                # Sort peers by tokens
                return sorted(entities.items(), key=lambda x: x[1]["tokens"], reverse=True)
            else:
                # Sort categories by tokens
                return sorted(entities.items(), key=lambda x: x[1]["tokens"], reverse=True)
                
        except Exception as e:
            logger.error(f"Error getting preference ranking: {e}")
            return []

# Create a singleton instance
feedback_loop = FeedbackLoop()

# Export the record function for external use
def record_task_result(task_id, prompt, category, model_used, peer_used, 
                      start_time, end_time, success, error_message=None, 
                      git_changes=None, token_usage=None):
    """Record a task result in the feedback loop."""
    return feedback_loop.record_task(
        task_id=task_id,
        prompt=prompt,
        category=category,
        model_used=model_used,
        peer_used=peer_used,
        start_time=start_time,
        end_time=end_time,
        success=success,
        error_message=error_message,
        git_changes=git_changes,
        token_usage=token_usage
    )