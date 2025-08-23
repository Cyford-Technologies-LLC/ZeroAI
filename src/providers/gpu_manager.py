"""GPU Provider Manager - Intelligently selects available GPU providers."""

from typing import List, Optional, Dict, Any
from crewai import LLM
from rich.console import Console

from ..env_loader import ENV
from .thunder_provider import ThunderComputeProvider
from .prime_provider import PrimeIntellectProvider

console = Console()


class GPUProviderManager:
    """Manages multiple GPU cloud providers with intelligent selection."""
    
    def __init__(self):
        self.thunder = ThunderComputeProvider()
        self.prime = PrimeIntellectProvider()
        self.active_provider = None
        
    def is_gpu_access_enabled(self) -> bool:
        """Check if GPU access is globally enabled."""
        return ENV.get("GPU_ACCESS_ENABLED", "false").lower() == "true"
    
    def get_provider_priority(self) -> List[str]:
        """Get GPU provider priority order from config."""
        priority_str = ENV.get("GPU_PROVIDER_PRIORITY", "thunder,prime")
        return [p.strip() for p in priority_str.split(",")]
    
    def get_available_providers(self) -> List[str]:
        """Get list of available and enabled GPU providers."""
        available = []
        
        if self.thunder.is_enabled() and self.thunder.is_available():
            available.append("thunder")
            
        if self.prime.is_enabled() and self.prime.is_available():
            available.append("prime")
            
        return available
    
    def select_best_provider(self) -> Optional[str]:
        """Select the best available GPU provider based on priority."""
        if not self.is_gpu_access_enabled():
            return None
            
        available = self.get_available_providers()
        if not available:
            console.print("⚠️  No GPU providers available", style="yellow")
            return None
        
        priority = self.get_provider_priority()
        
        # Select first available provider based on priority
        for provider in priority:
            if provider in available:
                console.print(f"🎯 Selected GPU provider: {provider}", style="green")
                return provider
        
        # Fallback to first available
        selected = available[0]
        console.print(f"🎯 Fallback GPU provider: {selected}", style="cyan")
        return selected
    
    def get_gpu_llm(
        self,
        model: str = "llama3.1:8b",
        temperature: float = 0.7,
        max_tokens: int = 4096,
        preferred_provider: Optional[str] = None
    ) -> Optional[LLM]:
        """Get LLM from best available GPU provider."""
        
        # Use preferred provider if specified and available
        if preferred_provider:
            if preferred_provider == "thunder" and self.thunder.is_enabled():
                return self.thunder.create_thunder_llm(model, temperature, max_tokens)
            elif preferred_provider == "prime" and self.prime.is_enabled():
                return self.prime.create_prime_llm(model, temperature, max_tokens)
        
        # Auto-select best provider
        provider = self.select_best_provider()
        if not provider:
            return None
        
        self.active_provider = provider
        
        if provider == "thunder":
            return self.thunder.create_thunder_llm(model, temperature, max_tokens)
        elif provider == "prime":
            return self.prime.create_prime_llm(model, temperature, max_tokens)
        
        return None
    
    def cleanup_resources(self):
        """Clean up active GPU resources."""
        if self.active_provider == "thunder":
            self.thunder.stop_instance()
        elif self.active_provider == "prime":
            self.prime.stop_session()
        
        self.active_provider = None
        console.print("🧹 GPU resources cleaned up", style="blue")
    
    def get_all_status(self) -> Dict[str, Any]:
        """Get status of all GPU providers."""
        return {
            "gpu_access_enabled": self.is_gpu_access_enabled(),
            "provider_priority": self.get_provider_priority(),
            "available_providers": self.get_available_providers(),
            "active_provider": self.active_provider,
            "thunder": self.thunder.get_status() if hasattr(self.thunder, 'get_status') else {
                "enabled": self.thunder.is_enabled(),
                "available": getattr(self.thunder, 'is_available', lambda: False)()
            },
            "prime": self.prime.get_status()
        }
    
    def show_status(self):
        """Display GPU provider status."""
        status = self.get_all_status()
        
        console.print("\n🎮 [bold]GPU Provider Status[/bold]")
        console.print("=" * 50)
        
        if not status["gpu_access_enabled"]:
            console.print("❌ GPU Access: Disabled", style="red")
            return
        
        console.print("✅ GPU Access: Enabled", style="green")
        console.print(f"🎯 Priority Order: {', '.join(status['provider_priority'])}")
        console.print(f"🟢 Available: {', '.join(status['available_providers']) or 'None'}")
        console.print(f"🔥 Active: {status['active_provider'] or 'None'}")
        
        console.print("\n📊 [bold]Provider Details:[/bold]")
        
        # Thunder status
        thunder = status["thunder"]
        thunder_status = "🟢" if thunder["enabled"] and thunder.get("available", False) else "🔴"
        console.print(f"{thunder_status} Thunder Compute: {'Enabled' if thunder['enabled'] else 'Disabled'}")
        
        # Prime status  
        prime = status["prime"]
        prime_status = "🟢" if prime["enabled"] and prime["available"] else "🔴"
        console.print(f"{prime_status} Prime Intellect: {'Enabled' if prime['enabled'] else 'Disabled'}")
        
        if status["active_provider"]:
            console.print(f"\n⚡ Currently using: {status['active_provider']}", style="yellow")
    
    def set_provider_priority(self, priority_list: List[str]):
        """Set GPU provider priority order."""
        valid_providers = ["thunder", "prime"]
        filtered_priority = [p for p in priority_list if p in valid_providers]
        
        if filtered_priority:
            ENV["GPU_PROVIDER_PRIORITY"] = ",".join(filtered_priority)
            console.print(f"🎯 GPU priority updated: {', '.join(filtered_priority)}", style="green")
        else:
            console.print("❌ Invalid provider names. Use: thunder, prime", style="red")
    
    def enable_gpu_access(self):
        """Enable GPU access globally."""
        ENV["GPU_ACCESS_ENABLED"] = "true"
        console.print("🎮 GPU access enabled", style="green")
    
    def disable_gpu_access(self):
        """Disable GPU access globally."""
        ENV["GPU_ACCESS_ENABLED"] = "false"
        self.cleanup_resources()
        console.print("❌ GPU access disabled", style="red")