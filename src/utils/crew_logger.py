import os
import json
from datetime import datetime
from pathlib import Path

class CrewLogger:
    def __init__(self, log_dir="/app/logs/crews"):
        self.log_dir = Path(log_dir)
        self.log_dir.mkdir(parents=True, exist_ok=True)
    
    def log_conversation(self, task_id, agent_role, prompt, response, timestamp=None):
        """Log crew conversation with task, agent, prompt, response, and timestamp"""
        if timestamp is None:
            timestamp = datetime.now().isoformat()
        
        log_entry = {
            "timestamp": timestamp,
            "task_id": task_id,
            "agent_role": agent_role,
            "prompt": prompt,
            "response": response
        }
        
        # Create daily log file
        date_str = datetime.now().strftime("%Y-%m-%d")
        log_file = self.log_dir / f"crew_conversations_{date_str}.jsonl"
        
        with open(log_file, "a", encoding="utf-8") as f:
            f.write(json.dumps(log_entry) + "\n")
    
    def get_recent_logs(self, days=7, task_id=None, agent_role=None):
        """Get recent crew conversation logs"""
        logs = []
        
        for i in range(days):
            date = datetime.now().date()
            if i > 0:
                from datetime import timedelta
                date = date - timedelta(days=i)
            
            log_file = self.log_dir / f"crew_conversations_{date}.jsonl"
            
            if log_file.exists():
                with open(log_file, "r", encoding="utf-8") as f:
                    for line in f:
                        try:
                            entry = json.loads(line.strip())
                            
                            # Filter by task_id if specified
                            if task_id and entry.get("task_id") != task_id:
                                continue
                            
                            # Filter by agent_role if specified
                            if agent_role and entry.get("agent_role") != agent_role:
                                continue
                            
                            logs.append(entry)
                        except json.JSONDecodeError:
                            continue
        
        # Sort by timestamp (newest first)
        logs.sort(key=lambda x: x.get("timestamp", ""), reverse=True)
        return logs
    
    def get_agent_performance_summary(self, agent_role, days=7):
        """Get performance summary for a specific agent"""
        logs = self.get_recent_logs(days=days, agent_role=agent_role)
        
        if not logs:
            return {"agent_role": agent_role, "total_interactions": 0, "recent_prompts": []}
        
        summary = {
            "agent_role": agent_role,
            "total_interactions": len(logs),
            "recent_prompts": [log["prompt"][:100] + "..." if len(log["prompt"]) > 100 else log["prompt"] for log in logs[:10]],
            "avg_response_length": sum(len(log["response"]) for log in logs) / len(logs) if logs else 0,
            "common_tasks": self._get_common_patterns(logs)
        }
        
        return summary
    
    def _get_common_patterns(self, logs):
        """Identify common task patterns from logs"""
        patterns = {}
        for log in logs:
            # Simple keyword extraction from prompts
            words = log["prompt"].lower().split()
            for word in words:
                if len(word) > 4:  # Only consider longer words
                    patterns[word] = patterns.get(word, 0) + 1
        
        # Return top 5 most common patterns
        return sorted(patterns.items(), key=lambda x: x[1], reverse=True)[:5]

# Global logger instance
crew_logger = CrewLogger()