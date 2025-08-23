"""Thunder Compute provider integration."""

import requests
import time
from typing import Optional, Dict, Any
from crewai import LLM
from ..env_loader import ENV
from rich.console import Console

console = Console()


class ThunderComputeProvider:
    """Manages Thunder Compute GPU instances for AI processing."""
    
    def __init__(self):
        self.api_key = ENV.get("THUNDER_API_KEY")
        self.instance_id = ENV.get("THUNDER_INSTANCE_ID")
        self.base_url = "https://api.thundercompute.com/v1"
        self.instance_endpoint = None
        
    def is_enabled(self) -> bool:
        """Check if Thunder Compute is enabled."""
        return ENV.get("THUNDER_ENABLED", "false").lower() == "true"
    
    def should_auto_start(self) -> bool:
        """Check if auto-start is enabled."""
        return ENV.get("THUNDER_AUTO_START", "false").lower() == "true"
    
    def get_complexity_threshold(self) -> int:
        """Get complexity threshold for auto-GPU usage."""
        return int(ENV.get("THUNDER_COMPLEXITY_THRESHOLD", "7"))
    
    def analyze_task_complexity(self, task_description: str) -> int:
        """Analyze task complexity on a scale of 1-10."""
        complexity_indicators = {
            "simple": ["chat", "hello", "basic", "quick", "simple"],
            "medium": ["analyze", "research", "write", "create", "explain"],
            "complex": ["comprehensive", "detailed", "complex", "advanced", "multi-step", "large"]
        }
        
        description_lower = task_description.lower()
        
        # Count complexity indicators
        simple_count = sum(1 for word in complexity_indicators["simple"] if word in description_lower)
        medium_count = sum(1 for word in complexity_indicators["medium"] if word in description_lower)
        complex_count = sum(1 for word in complexity_indicators["complex"] if word in description_lower)
        
        # Calculate complexity score
        if complex_count > 0 or len(description_lower) > 200:
            return 8 + min(complex_count, 2)
        elif medium_count > 0 or len(description_lower) > 100:
            return 5 + min(medium_count, 2)
        else:
            return 2 + min(simple_count, 3)
    
    def start_instance(self) -> bool:
        """Start Thunder Compute instance."""
        if not self.api_key or not self.instance_id:
            console.print("❌ Thunder Compute credentials not configured", style="red")
            return False
            
        try:
            console.print("🚀 Starting Thunder Compute instance...", style="yellow")
            
            # Simulate API call (replace with actual Thunder API)
            headers = {"Authorization": f"Bearer {self.api_key}"}
            response = requests.post(
                f"{self.base_url}/instances/{self.instance_id}/start",
                headers=headers,
                timeout=30
            )
            
            if response.status_code == 200:
                # Wait for instance to be ready
                self._wait_for_instance_ready()
                console.print("✅ Thunder Compute instance started", style="green")
                return True
            else:
                console.print(f"❌ Failed to start instance: {response.status_code}", style="red")
                return False
                
        except Exception as e:
            console.print(f"❌ Thunder Compute error: {e}", style="red")
            return False
    
    def stop_instance(self) -> bool:
        """Stop Thunder Compute instance."""
        if not self.api_key or not self.instance_id:
            return False
            
        try:
            console.print("⏹️  Stopping Thunder Compute instance...", style="yellow")
            
            headers = {"Authorization": f"Bearer {self.api_key}"}
            response = requests.post(
                f"{self.base_url}/instances/{self.instance_id}/stop",
                headers=headers,
                timeout=30
            )
            
            if response.status_code == 200:
                console.print("✅ Thunder Compute instance stopped", style="green")
                return True
            else:
                console.print(f"❌ Failed to stop instance: {response.status_code}", style="red")
                return False
                
        except Exception as e:
            console.print(f"❌ Thunder Compute error: {e}", style="red")
            return False
    
    def _wait_for_instance_ready(self, max_wait: int = 300) -> bool:
        """Wait for instance to be ready."""
        console.print("⏳ Waiting for instance to be ready...", style="yellow")
        
        for i in range(max_wait):
            if self._check_instance_status():
                return True
            time.sleep(1)
            
        console.print("❌ Instance startup timeout", style="red")
        return False
    
    def _check_instance_status(self) -> bool:
        """Check if instance is ready."""
        try:
            # Check if Ollama is responding on the instance
            response = requests.get(f"{self.get_instance_endpoint()}/api/tags", timeout=5)
            return response.status_code == 200
        except:
            return False
    
    def get_instance_endpoint(self) -> str:
        """Get the endpoint URL for the Thunder instance."""
        if not self.instance_endpoint:
            # This would be provided by Thunder Compute API
            self.instance_endpoint = f"http://thunder-{self.instance_id}.compute.com:11434"
        return self.instance_endpoint
    
    def create_thunder_llm(
        self,
        model: str = "llama3.1:8b",
        temperature: float = 0.7,
        max_tokens: int = 4096
    ) -> Optional[LLM]:
        """Create LLM connection to Thunder Compute instance."""
        if not self.is_enabled():
            return None
            
        try:
            return LLM(
                model=f"ollama/{model}",
                base_url=self.get_instance_endpoint(),
                temperature=temperature,
                max_tokens=max_tokens
            )
        except Exception as e:
            console.print(f"❌ Failed to create Thunder LLM: {e}", style="red")
            return None