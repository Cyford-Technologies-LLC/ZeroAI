# src/learning/feedback_loop.py

import logging
import json
import time
from datetime import datetime
from pathlib import Path
from typing import Dict, Any, List, Optional
from pydantic import BaseModel

class TaskOutcome(BaseModel):
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

class FeedbackLoop:
    def __init__(self, database_path: str = "knowledge/learning/task_outcomes.json"):
        self.database_path = Path(database_path)
        self.database_path.parent.mkdir(parents=True, exist_ok=True)
        self.logger = logging.getLogger("feedback_loop")
        
        # Initialize the database if it doesn't exist
        if not self.database_path.exists():
            with open(self.database_path, 'w') as f:
                json.dump({"task_outcomes": []}, f)
        
    def record_task_outcome(self, outcome: TaskOutcome):
        """Record the outcome of a task execution"""
        try:
            # Load existing data
            with open(self.database_path, 'r') as f:
                data = json.load(f)
            
            # Add new outcome
            data["task_outcomes"].append(outcome.dict())
            
            # Save updated data
            with open(self.database_path, 'w') as f:
                json.dump(data, f, indent=2)
                
            self.logger.info(f"Recorded outcome for task {outcome.task_id}")
            return True
        except Exception as e:
            self.logger.error(f"Failed to record task outcome: {e}")
            return False
    
    def get_model_success_rates(self) -> Dict[str, float]:
        """Calculate success rates for different models"""
        try:
            with open(self.database_path, 'r') as f:
                data = json.load(f)
            
            model_stats = {}
            for outcome in data["task_outcomes"]:
                model = outcome["model_used"]
                if model not in model_stats:
                    model_stats[model] = {"success": 0, "total": 0}
                
                model_stats[model]["total"] += 1
                if outcome["success"]:
                    model_stats[model]["success"] += 1
            
            # Calculate success rates
            success_rates = {}
            for model, stats in model_stats.items():
                if stats["total"] > 0:
                    success_rates[model] = stats["success"] / stats["total"]
                else:
                    success_rates[model] = 0.0
                    
            return success_rates
        except Exception as e:
            self.logger.error(f"Failed to calculate model success rates: {e}")
            return {}
    
    def get_category_model_mapping(self) -> Dict[str, List[str]]:
        """Learn which models work best for which categories"""
        try:
            with open(self.database_path, 'r') as f:
                data = json.load(f)
            
            # Group outcomes by category and model
            category_model_stats = {}
            for outcome in data["task_outcomes"]:
                category = outcome["category"]
                model = outcome["model_used"]
                
                if category not in category_model_stats:
                    category_model_stats[category] = {}
                
                if model not in category_model_stats[category]:
                    category_model_stats[category][model] = {"success": 0, "total": 0}
                
                category_model_stats[category][model]["total"] += 1
                if outcome["success"]:
                    category_model_stats[category][model]["success"] += 1
            
            # For each category, sort models by success rate
            category_model_mapping = {}
            for category, model_stats in category_model_stats.items():
                models_with_rates = []
                for model, stats in model_stats.items():
                    if stats["total"] > 0:
                        success_rate = stats["success"] / stats["total"]
                        models_with_rates.append((model, success_rate))
                
                # Sort by success rate (descending)
                models_with_rates.sort(key=lambda x: x[1], reverse=True)
                
                # Extract just the model names
                category_model_mapping[category] = [model for model, _ in models_with_rates]
            
            return category_model_mapping
        except Exception as e:
            self.logger.error(f"Failed to generate category-model mapping: {e}")
            return {}