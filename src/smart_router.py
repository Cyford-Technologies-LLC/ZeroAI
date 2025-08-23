"""Smart routing system for ZeroAI."""

import requests
from typing import Optional, Dict, Any
from env_loader import ENV

class SmartRouter:
    """Routes tasks between local and GPU processing."""
    
    def __init__(self):
        self.gpu_enabled = ENV.get("GPU_ACCESS_ENABLED", "false").lower() == "true"
        self.prime_enabled = ENV.get("PRIME_ENABLED", "false").lower() == "true"
        self.prime_url = ENV.get("PRIME_GPU_BRIDGE_URL")
        
    def should_use_gpu(self, task: str, complexity: int = 5) -> bool:
        """Determine if task should use GPU based on complexity."""
        if not self.gpu_enabled or not self.prime_enabled:
            return False
            
        # Use GPU for complex tasks or long prompts
        if complexity >= 7 or len(task) > 200:
            return True
            
        return False
    
    def process_with_gpu(self, task: str, model: str = "llama3.2:1b") -> Optional[str]:
        """Process task using GPU bridge."""
        if not self.prime_url:
            return None
            
        try:
            response = requests.post(
                f"{self.prime_url}/process",
                json={
                    "task": task,
                    "model": model,
                    "temperature": 0.7,
                    "max_tokens": 512
                },
                timeout=60
            )
            
            if response.status_code == 200:
                return response.json().get("result")
                
        except Exception as e:
            print(f"GPU processing failed: {e}")
            
        return None
    
    def get_optimal_base_url(self, task: str = "", complexity: int = 5) -> str:
        """Get optimal base URL for processing."""
        if self.should_use_gpu(task, complexity):
            return self.prime_url or "http://localhost:11434"
        return "http://localhost:11434"

# Global router instance
router = SmartRouter()