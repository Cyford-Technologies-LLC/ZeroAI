"""Manual GPU provider for pre-configured instances."""

from typing import Optional, Dict, Any
from crewai import LLM
from ..env_loader import ENV
from rich.console import Console

console = Console()


class ManualGPUProvider:
    """Manages manually configured GPU instances (Prime Intellect, RunPod, etc.)."""
    
    def __init__(self):
        self.endpoint = ENV.get("MANUAL_GPU_ENDPOINT")
        self.instance_name = ENV.get("MANUAL_GPU_NAME", "Manual GPU")
        
    def is_enabled(self) -> bool:
        """Check if manual GPU is enabled."""
        return ENV.get("MANUAL_GPU_ENABLED", "false").lower() == "true"
    
    def is_available(self) -> bool:
        """Check if manual GPU endpoint is accessible."""
        if not self.is_enabled() or not self.endpoint:
            return False
            
        try:
            import requests
            response = requests.get(f"{self.endpoint}/api/tags", timeout=10)
            return response.status_code == 200
        except Exception as e:
            console.print(f"⚠️  {self.instance_name} unavailable: {e}", style="yellow")
            return False
    
    def create_manual_gpu_llm(
        self,
        model: str = "llama3.1:8b",
        temperature: float = 0.7,
        max_tokens: int = 4096
    ) -> Optional[LLM]:
        """Create LLM connection to manual GPU instance."""
        if not self.is_enabled() or not self.endpoint:
            return None
            
        try:
            return LLM(
                model=f"ollama/{model}",
                base_url=self.endpoint,
                temperature=temperature,
                max_tokens=max_tokens
            )
        except Exception as e:
            console.print(f"❌ Failed to create {self.instance_name} LLM: {e}", style="red")
            return None
    
    def get_status(self) -> Dict[str, Any]:
        """Get manual GPU provider status."""
        return {
            "enabled": self.is_enabled(),
            "available": self.is_available(),
            "endpoint": self.endpoint,
            "instance_name": self.instance_name,
            "manual_management": True
        }