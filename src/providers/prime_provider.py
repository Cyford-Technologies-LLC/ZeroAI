"""Prime Intellect provider integration."""

import requests
import time
from typing import Optional, Dict, Any
from crewai import LLM
from ..env_loader import ENV
from rich.console import Console

console = Console()


class PrimeIntellectProvider:
    """Manages Prime Intellect GPU instances (powered by RunPod) for AI processing."""
    
    def __init__(self):
        self.api_key = ENV.get("PRIME_API_KEY")
        self.endpoint = ENV.get("PRIME_ENDPOINT", "https://api.primeintellect.ai/v1")
        self.instance_id = ENV.get("PRIME_INSTANCE_ID")  # Your RunPod instance ID
        self.session_id = None
        
    def is_enabled(self) -> bool:
        """Check if Prime Intellect is enabled."""
        return ENV.get("PRIME_ENABLED", "false").lower() == "true"
    
    def should_auto_start(self) -> bool:
        """Check if auto-start is enabled."""
        return ENV.get("PRIME_AUTO_START", "false").lower() == "true"
    
    def is_available(self) -> bool:
        """Check if Prime Intellect is available and accessible."""
        if not self.is_enabled() or not self.api_key:
            return False
            
        try:
            headers = {"Authorization": f"Bearer {self.api_key}"}
            response = requests.get(
                f"{self.endpoint}/health",
                headers=headers,
                timeout=10
            )
            return response.status_code == 200
        except Exception as e:
            console.print(f"⚠️  Prime Intellect unavailable: {e}", style="yellow")
            return False
    
    def start_session(self) -> bool:
        """Start Prime Intellect GPU session."""
        if not self.api_key:
            console.print("❌ Prime Intellect API key not configured", style="red")
            return False
            
        try:
            console.print("🚀 Starting Prime Intellect session...", style="yellow")
            
            headers = {"Authorization": f"Bearer {self.api_key}"}
            payload = {
                "model": "llama-3.1-8b",
                "gpu_type": "A100",
                "auto_shutdown": 1800  # 30 minutes
            }
            
            response = requests.post(
                f"{self.endpoint}/sessions",
                headers=headers,
                json=payload,
                timeout=30
            )
            
            if response.status_code == 200:
                data = response.json()
                self.session_id = data.get("session_id")
                console.print("✅ Prime Intellect session started", style="green")
                return True
            else:
                console.print(f"❌ Failed to start session: {response.status_code}", style="red")
                return False
                
        except Exception as e:
            console.print(f"❌ Prime Intellect error: {e}", style="red")
            return False
    
    def stop_session(self) -> bool:
        """Stop Prime Intellect GPU session."""
        if not self.session_id or not self.api_key:
            return False
            
        try:
            console.print("⏹️  Stopping Prime Intellect session...", style="yellow")
            
            headers = {"Authorization": f"Bearer {self.api_key}"}
            response = requests.delete(
                f"{self.endpoint}/sessions/{self.session_id}",
                headers=headers,
                timeout=30
            )
            
            if response.status_code == 200:
                console.print("✅ Prime Intellect session stopped", style="green")
                self.session_id = None
                return True
            else:
                console.print(f"❌ Failed to stop session: {response.status_code}", style="red")
                return False
                
        except Exception as e:
            console.print(f"❌ Prime Intellect error: {e}", style="red")
            return False
    
    def create_prime_llm(
        self,
        model: str = "llama-3.1-8b",
        temperature: float = 0.7,
        max_tokens: int = 4096
    ) -> Optional[LLM]:
        """Create LLM connection to Prime Intellect."""
        if not self.is_enabled():
            return None
            
        try:
            # Start session if auto-start is enabled and no active session
            if self.should_auto_start() and not self.session_id:
                if not self.start_session():
                    return None
            
            return LLM(
                model=f"prime/{model}",
                api_key=self.api_key,
                base_url=self.endpoint,
                temperature=temperature,
                max_tokens=max_tokens
            )
        except Exception as e:
            console.print(f"❌ Failed to create Prime LLM: {e}", style="red")
            return None
    
    def get_status(self) -> Dict[str, Any]:
        """Get Prime Intellect provider status."""
        return {
            "enabled": self.is_enabled(),
            "available": self.is_available(),
            "auto_start": self.should_auto_start(),
            "session_active": bool(self.session_id),
            "session_id": self.session_id
        }