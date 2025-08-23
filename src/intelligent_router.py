"""Intelligent task routing system for optimal AI provider selection."""

from typing import Dict, Any, Optional
from crewai import LLM
from rich.console import Console

from .env_loader import ENV
from .providers.cloud_providers import CloudProviderManager
from .providers.gpu_manager import GPUProviderManager
from .config import config

console = Console()


class IntelligentAIRouter:
    """Routes AI tasks to the optimal provider based on complexity and settings."""
    
    def __init__(self):
        self.gpu_manager = GPUProviderManager()
        self.cloud = CloudProviderManager()
        self.local_llm = None
        self.gpu_llm = None
        
    def route_task(self, task_description: str, force_provider: Optional[str] = None) -> LLM:
        """Route task to optimal AI provider."""
        
        # Check for forced local-only mode
        if ENV.get("LOCAL_ONLY_MODE", "false").lower() == "true":
            console.print("🏠 Local-only mode enabled", style="blue")
            return self._get_local_llm()
        
        # Use forced provider if specified
        if force_provider:
            return self._get_provider_llm(force_provider)
        
        # Analyze task complexity
        complexity = self._analyze_task_complexity(task_description)
        console.print(f"📊 Task complexity: {complexity}/10", style="cyan")
        
        # Route based on complexity and GPU settings
        complexity_threshold = int(ENV.get("THUNDER_COMPLEXITY_THRESHOLD", "7"))
        
        if (self.gpu_manager.is_gpu_access_enabled() and 
            complexity >= complexity_threshold):
            
            console.print("⚡ Routing to GPU provider", style="yellow")
            return self._get_gpu_llm()
        
        else:
            console.print("🏠 Routing to local processing", style="blue")
            return self._get_local_llm()
    
    def _get_local_llm(self) -> LLM:
        """Get local Ollama LLM."""
        if not self.local_llm:
            self.local_llm = LLM(
                model=f"ollama/{config.model.name}",
                base_url=config.model.base_url,
                temperature=config.model.temperature,
                max_tokens=config.model.max_tokens
            )
        return self.local_llm
    
    def _get_gpu_llm(self) -> LLM:
        """Get GPU LLM from best available provider."""
        if not self.gpu_llm:
            self.gpu_llm = self.gpu_manager.get_gpu_llm()
            
            if not self.gpu_llm:
                console.print("⚠️  No GPU providers available, falling back to local", style="yellow")
                return self._get_local_llm()
        
        return self.gpu_llm
    
    def _analyze_task_complexity(self, task_description: str) -> int:
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
    
    def _get_provider_llm(self, provider: str) -> LLM:
        """Get LLM for specific provider."""
        if provider == "local":
            return self._get_local_llm()
        elif provider == "gpu":
            return self._get_gpu_llm()
        elif provider == "thunder":
            return self.gpu_manager.get_gpu_llm(preferred_provider="thunder")
        elif provider == "prime":
            return self.gpu_manager.get_gpu_llm(preferred_provider="prime")
        elif provider == "openai":
            return self.cloud.create_openai_llm()
        elif provider == "anthropic":
            return self.cloud.create_anthropic_llm()
        elif provider == "google":
            return self.cloud.create_google_llm()
        else:
            console.print(f"⚠️  Unknown provider '{provider}', using local", style="yellow")
            return self._get_local_llm()
    
    def set_gpu_mode(self, enabled: bool, threshold: int = 7, priority: List[str] = None):
        """Configure GPU access settings."""
        if enabled:
            self.gpu_manager.enable_gpu_access()
        else:
            self.gpu_manager.disable_gpu_access()
            
        ENV["THUNDER_COMPLEXITY_THRESHOLD"] = str(threshold)
        
        if priority:
            self.gpu_manager.set_provider_priority(priority)
        
        console.print(f"🎯 Complexity threshold: {threshold}/10", style="cyan")
    
    def set_thunder_mode(self, enabled: bool, auto_start: bool = True, threshold: int = 7):
        """Legacy method - configure Thunder Compute settings."""
        ENV["THUNDER_ENABLED"] = str(enabled).lower()
        ENV["THUNDER_AUTO_START"] = str(auto_start).lower()
        self.set_gpu_mode(enabled, threshold, ["thunder"])
    
    def set_local_only_mode(self, enabled: bool):
        """Enable/disable local-only mode."""
        ENV["LOCAL_ONLY_MODE"] = str(enabled).lower()
        mode_desc = "enabled" if enabled else "disabled"
        console.print(f"🏠 Local-only mode {mode_desc}", style="blue")
    
    def get_status(self) -> Dict[str, Any]:
        """Get current router status."""
        gpu_status = self.gpu_manager.get_all_status()
        return {
            "gpu_access_enabled": gpu_status["gpu_access_enabled"],
            "available_gpu_providers": gpu_status["available_providers"],
            "active_gpu_provider": gpu_status["active_provider"],
            "provider_priority": gpu_status["provider_priority"],
            "complexity_threshold": int(ENV.get("THUNDER_COMPLEXITY_THRESHOLD", "7")),
            "local_only_mode": ENV.get("LOCAL_ONLY_MODE", "false").lower() == "true",
            "gpu_providers": gpu_status
        }
    
    def show_status(self):
        """Display current router status."""
        self.gpu_manager.show_status()
        
        status = self.get_status()
        console.print(f"\n🎯 Complexity Threshold: {status['complexity_threshold']}/10")
        console.print(f"🏠 Local Only Mode: {status['local_only_mode']}")
    
    def cleanup(self):
        """Clean up router resources."""
        self.gpu_manager.cleanup_resources()