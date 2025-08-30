# /opt/ZeroAI/src/learning/feedback_loop.py

import logging
import json
import time
from datetime import datetime
from pathlib import Path
from typing import Dict, Any, List, Optional
from pydantic import BaseModel, Field
from rich.console import Console

console = Console()

class TaskOutcome(BaseModel):
    """Record of a task execution and its outcome"""
    task_id: str
    prompt: str
    category: str
    model_used: str
    peer_used: str
    start_time: float
    end_time: float
    success: bool
    error_message: Optional[str] = None
    git_changes: Optional[Dict[str, Any]] = None
    token_usage: Optional[Dict[str, int]] = None
    
    # Token-based metrics for reinforcement learning
    tokens_earned: int = Field(default=0, description="Success tokens earned by this execution")
    execution_time: float = Field(default=0, description="Time taken to execute in seconds")
    
    def calculate_tokens(self) -> int:
        """Calculate tokens earned based on outcomes"""
        tokens = 0
        
        # Base tokens for successful completion
        if self.success:
            tokens += 10
        else:
            return 0  # No tokens for failed tasks
        
        # Speed bonus (faster = more tokens, up to 5 extra)
        exec_time = self.end_time - self.start_time
        self.execution_time = exec_time
        if exec_time < 60:  # Under 1 minute
            tokens += 5
        elif exec_time < 300:  # Under 5 minutes
            tokens += 4
        elif exec_time < 600:  # Under 10 minutes
            tokens += 3
        elif exec_time < 1800:  # Under 30 minutes
            tokens += 2
        elif exec_time < 3600:  # Under 1 hour
            tokens += 1
        
        # Efficiency bonus based on token usage (if available)
        if self.token_usage and "total_tokens" in self.token_usage:
            total_tokens = self.token_usage["total_tokens"]
            if total_tokens < 1000:
                tokens += 3
            elif total_tokens < 2000:
                tokens += 2
            elif total_tokens < 4000:
                tokens += 1
        
        # Git changes bonus (more impactful changes = more tokens)
        if self.git_changes:
            # Bonus for making actual code changes
            if "files_changed" in self.git_changes:
                files_changed = int(self.git_changes["files_changed"])
                tokens += min(files_changed, 3)  # Up to 3 tokens for file changes
        
        self.tokens_earned = tokens
        return tokens


class LearningMetrics(BaseModel):
    """Aggregate learning metrics for models, categories, and peers"""
    last_updated: float = Field(default_factory=time.time)
    
    # Model performance metrics
    model_metrics: Dict[str, Dict[str, Any]] = Field(default_factory=dict)
    
    # Category-specific metrics
    category_metrics: Dict[str, Dict[str, Any]] = Field(default_factory=dict)
    
    # Peer performance metrics
    peer_metrics: Dict[str, Dict[str, Any]] = Field(default_factory=dict)
    
    # Keyword to category mapping success rates
    keyword_metrics: Dict[str, Dict[str, Any]] = Field(default_factory=dict)


class FeedbackLoop:
    """System for recording task outcomes and learning from them"""
    
    def __init__(self, database_path: str = "knowledge/learning/task_outcomes.json",
                 metrics_path: str = "knowledge/learning/learning_metrics.json"):
        self.database_path = Path(database_path)
        self.metrics_path = Path(metrics_path)
        self.logger = logging.getLogger("feedback_loop")
        
        # Initialize directories
        self.database_path.parent.mkdir(parents=True, exist_ok=True)
        
        # Initialize the outcomes database if it doesn't exist
        if not self.database_path.exists():
            with open(self.database_path, 'w') as f:
                json.dump({"task_outcomes": []}, f, indent=2)
        
        # Initialize the metrics file if it doesn't exist
        if not self.metrics_path.exists():
            metrics = LearningMetrics()
            with open(self.metrics_path, 'w') as f:
                json.dump(metrics.dict(), f, indent=2)
        
        console.print(f"📊 Feedback loop initialized with database at {self.database_path}", style="green")
    
    def record_task_outcome(self, outcome: TaskOutcome) -> bool:
        """Record the outcome of a task execution and update learning metrics"""
        try:
            # Calculate tokens earned
            outcome.calculate_tokens()
            
            # Load existing outcomes
            with open(self.database_path, 'r') as f:
                data = json.load(f)
            
            # Add new outcome
            data["task_outcomes"].append(outcome.dict())
            
            # Save updated outcomes
            with open(self.database_path, 'w') as f:
                json.dump(data, f, indent=2)
            
            # Update learning metrics
            self._update_learning_metrics(outcome)
            
            self.logger.info(f"Recorded outcome for task {outcome.task_id} with {outcome.tokens_earned} tokens earned")
            console.print(f"📝 Recorded task outcome: {outcome.tokens_earned} tokens earned", style="green")
            return True
        
        except Exception as e:
            self.logger.error(f"Failed to record task outcome: {e}")
            console.print(f"❌ Failed to record task outcome: {e}", style="red")
            return False
    
    def _update_learning_metrics(self, outcome: TaskOutcome) -> None:
        """Update learning metrics based on a new task outcome"""
        try:
            # Load current metrics
            with open(self.metrics_path, 'r') as f:
                metrics_dict = json.load(f)
            
            metrics = LearningMetrics(**metrics_dict)
            metrics.last_updated = time.time()
            
            # Update model metrics
            model = outcome.model_used
            if model not in metrics.model_metrics:
                metrics.model_metrics[model] = {
                    "success_count": 0,
                    "failure_count": 0,
                    "total_tokens": 0,
                    "total_time": 0,
                    "average_tokens_per_task": 0,
                    "categories": {}
                }
            
            if outcome.success:
                metrics.model_metrics[model]["success_count"] += 1
            else:
                metrics.model_metrics[model]["failure_count"] += 1
            
            metrics.model_metrics[model]["total_tokens"] += outcome.tokens_earned
            metrics.model_metrics[model]["total_time"] += outcome.execution_time
            
            # Update category tracking for this model
            category = outcome.category
            if category not in metrics.model_metrics[model]["categories"]:
                metrics.model_metrics[model]["categories"][category] = {
                    "success_count": 0,
                    "failure_count": 0,
                    "total_tokens": 0
                }
            
            if outcome.success:
                metrics.model_metrics[model]["categories"][category]["success_count"] += 1
            else:
                metrics.model_metrics[model]["categories"][category]["failure_count"] += 1
            
            metrics.model_metrics[model]["categories"][category]["total_tokens"] += outcome.tokens_earned
            
            # Update category metrics
            if category not in metrics.category_metrics:
                metrics.category_metrics[category] = {
                    "success_count": 0,
                    "failure_count": 0,
                    "total_tokens": 0,
                    "best_models": []
                }
            
            if outcome.success:
                metrics.category_metrics[category]["success_count"] += 1
            else:
                metrics.category_metrics[category]["failure_count"] += 1
            
            metrics.category_metrics[category]["total_tokens"] += outcome.tokens_earned
            
            # Update peer metrics
            peer = outcome.peer_used
            if peer not in metrics.peer_metrics:
                metrics.peer_metrics[peer] = {
                    "success_count": 0,
                    "failure_count": 0,
                    "total_tokens": 0,
                    "models": {}
                }
            
            if outcome.success:
                metrics.peer_metrics[peer]["success_count"] += 1
            else:
                metrics.peer_metrics[peer]["failure_count"] += 1
            
            metrics.peer_metrics[peer]["total_tokens"] += outcome.tokens_earned
            
            # Update peer's model tracking
            if model not in metrics.peer_metrics[peer]["models"]:
                metrics.peer_metrics[peer]["models"][model] = {
                    "success_count": 0,
                    "failure_count": 0,
                    "total_tokens": 0
                }
            
            if outcome.success:
                metrics.peer_metrics[peer]["models"][model]["success_count"] += 1
            else:
                metrics.peer_metrics[peer]["models"][model]["failure_count"] += 1
            
            metrics.peer_metrics[peer]["models"][model]["total_tokens"] += outcome.tokens_earned
            
            # Update keyword metrics based on the prompt
            # This helps refine KEYWORDS_TO_CATEGORY mapping
            for keyword in self._extract_keywords(outcome.prompt):
                if keyword not in metrics.keyword_metrics:
                    metrics.keyword_metrics[keyword] = {
                        "categories": {},
                        "total_uses": 0
                    }
                
                metrics.keyword_metrics[keyword]["total_uses"] += 1
                
                if category not in metrics.keyword_metrics[keyword]["categories"]:
                    metrics.keyword_metrics[keyword]["categories"][category] = {
                        "count": 0,
                        "total_tokens": 0
                    }
                
                metrics.keyword_metrics[keyword]["categories"][category]["count"] += 1
                metrics.keyword_metrics[keyword]["categories"][category]["total_tokens"] += outcome.tokens_earned
            
            # Save updated metrics
            with open(self.metrics_path, 'w') as f:
                json.dump(metrics.dict(), f, indent=2)
            
            # Recalculate best models for each category
            self._update_category_best_models()
        
        except Exception as e:
            self.logger.error(f"Failed to update learning metrics: {e}")
            console.print(f"❌ Failed to update learning metrics: {e}", style="red")
    
    def _extract_keywords(self, text: str) -> List[str]:
        """Extract potential keywords from text for mapping refinement"""
        # For now, a simple approach: split text into words and keep those with length > 3
        words = [word.lower() for word in text.split() if len(word) > 3]
        return list(set(words))  # Remove duplicates
    
    def _update_category_best_models(self) -> None:
        """Recalculate the best models for each category based on token performance"""
        try:
            with open(self.metrics_path, 'r') as f:
                metrics_dict = json.load(f)
            
            metrics = LearningMetrics(**metrics_dict)
            
            # For each category, calculate the best models
            for category, category_data in metrics.category_metrics.items():
                # Find all models that have been used for this category
                models_for_category = []
                
                for model, model_data in metrics.model_metrics.items():
                    if category in model_data["categories"]:
                        cat_data = model_data["categories"][category]
                        total = cat_data["success_count"] + cat_data["failure_count"]
                        
                        if total > 0:
                            success_rate = cat_data["success_count"] / total
                            tokens_per_task = cat_data["total_tokens"] / total if total > 0 else 0
                            
                            models_for_category.append({
                                "model": model,
                                "success_rate": success_rate,
                                "tokens_per_task": tokens_per_task,
                                "total_tasks": total
                            })
                
                # Sort models by success rate and tokens per task (weighted scoring)
                def model_score(model_info):
                    # Weight success rate higher than tokens
                    return (model_info["success_rate"] * 0.7) + (model_info["tokens_per_task"] / 20 * 0.3)
                
                models_for_category.sort(key=model_score, reverse=True)
                
                # Update the best models list
                metrics.category_metrics[category]["best_models"] = [
                    m["model"] for m in models_for_category[:5]  # Keep top 5
                ]
            
            # Save updated metrics
            with open(self.metrics_path, 'w') as f:
                json.dump(metrics.dict(), f, indent=2)
        
        except Exception as e:
            self.logger.error(f"Failed to update category best models: {e}")
    
    def get_model_success_rates(self) -> Dict[str, float]:
        """Calculate success rates for different models"""
        try:
            with open(self.metrics_path, 'r') as f:
                metrics_dict = json.load(f)
            
            metrics = LearningMetrics(**metrics_dict)
            
            # Calculate success rates
            success_rates = {}
            for model, data in metrics.model_metrics.items():
                total = data["success_count"] + data["failure_count"]
                if total > 0:
                    success_rates[model] = data["success_count"] / total
                else:
                    success_rates[model] = 0.0
            
            return success_rates
        
        except Exception as e:
            self.logger.error(f"Failed to calculate model success rates: {e}")
            return {}
    
    def get_category_model_mapping(self) -> Dict[str, List[str]]:
        """Get learned model preferences for each category"""
        try:
            with open(self.metrics_path, 'r') as f:
                metrics_dict = json.load(f)
            
            metrics = LearningMetrics(**metrics_dict)
            
            # Extract category to model mappings
            mapping = {}
            for category, data in metrics.category_metrics.items():
                if "best_models" in data and data["best_models"]:
                    mapping[category] = data["best_models"]
            
            return mapping
        
        except Exception as e:
            self.logger.error(f"Failed to get category model mapping: {e}")
            return {}
    
    def get_improved_keyword_mapping(self) -> Dict[str, str]:
        """Generate improved keyword to category mappings based on learning"""
        try:
            with open(self.metrics_path, 'r') as f:
                metrics_dict = json.load(f)
            
            metrics = LearningMetrics(**metrics_dict)
            
            # For each keyword, find the most successful category
            keyword_to_category = {}
            for keyword, data in metrics.keyword_metrics.items():
                if data["total_uses"] < 3:
                    continue  # Skip keywords with too few uses
                
                best_category = None
                best_score = 0
                
                for category, cat_data in data["categories"].items():
                    # Score based on count and tokens
                    score = cat_data["count"] * (1 + cat_data["total_tokens"] / (cat_data["count"] * 10))
                    
                    if score > best_score:
                        best_score = score
                        best_category = category
                
                if best_category:
                    keyword_to_category[keyword] = best_category
            
            return keyword_to_category
        
        except Exception as e:
            self.logger.error(f"Failed to generate improved keyword mapping: {e}")
            return {}