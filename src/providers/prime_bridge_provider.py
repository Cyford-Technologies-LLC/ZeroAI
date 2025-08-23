"""Prime Intellect GPU Bridge provider for ZeroAI."""

import requests
from typing import Optional, Dict, Any
from crewai import LLM
from ..env_loader import ENV
from rich.console import Console

console = Console()


class PrimeBridgeProvider:
    """Connects to Prime Intellect GPU bridge API."""
    
    def __init__(self):
        self.bridge_url = ENV.get("PRIME_GPU_BRIDGE_URL")
        
    def is_enabled(self) -> bool:
        """Check if Prime GPU bridge is enabled."""
        return ENV.get("PRIME_ENABLED", "false").lower() == "true"
    
    def is_available(self) -> bool:
        """Check if Prime GPU bridge is accessible."""
        if not self.is_enabled() or not self.bridge_url:
            return False
            
        try:
            response = requests.get(f"{self.bridge_url}/health", timeout=10)
            return response.status_code == 200
        except Exception as e:
            console.print(f"⚠️  Prime GPU bridge unavailable: {e}", style="yellow")
            return False
    
    def create_prime_llm(
        self,
        model: str = "llama3.1:8b",
        temperature: float = 0.7,
        max_tokens: int = 4096
    ) -> Optional[LLM]:
        """Create LLM connection to Prime GPU bridge."""
        if not self.is_enabled() or not self.bridge_url:
            return None
            
        try:
            return LLM(
                model=f"ollama/{model}",
                base_url=self.bridge_url,
                temperature=temperature,
                max_tokens=max_tokens
            )
        except Exception as e:
            console.print(f"❌ Failed to create Prime bridge LLM: {e}", style="red")
            return None
    
    def get_status(self) -> Dict[str, Any]:
        """Get Prime GPU bridge status."""
        return {
            "enabled": self.is_enabled(),
            "available": self.is_available(),
            "bridge_url": self.bridge_url,
            "provider": "Prime Intellect GPU Bridge"
        }