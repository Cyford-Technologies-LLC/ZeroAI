"""
ZeroAI - Main Framework Class

Zero Cost. Zero Cloud. Zero Limits.
"""

from typing import Dict, Any, Optional, List
from crewai import Crew
from rich.console import Console

from .intelligent_router import IntelligentAIRouter
from .smart_ai_manager import SmartAIManager
from .agents.base_agents import create_researcher, create_writer, create_analyst
from .tasks.base_tasks import create_research_task, create_writing_task, create_analysis_task

console = Console()


class ZeroAI:
    """
    ZeroAI - The ultimate self-hosted AI framework.
    
    Zero Cost. Zero Cloud. Zero Limits.
    """
    
    def __init__(self, mode: str = "smart", **kwargs):
        """
        Initialize ZeroAI.
        
        Args:
            mode: "local" (zero cost), "smart" (optimal cost), or "cloud" (max power)
            **kwargs: Additional configuration options
        """
        self.mode = mode
        self.manager = SmartAIManager()
        self.router = IntelligentAIRouter()
        
        console.print("💰 [bold blue]ZeroAI Initialized[/bold blue]")
        console.print(f"🎯 Mode: {mode.title()}")
        
        # Configure based on mode
        if mode == "local":
            self._setup_local_mode()
        elif mode == "smart":
            self._setup_smart_mode(**kwargs)
        elif mode == "cloud":
            self._setup_cloud_mode(**kwargs)
        else:
            console.print("⚠️  Unknown mode, defaulting to smart", style="yellow")
            self._setup_smart_mode(**kwargs)
    
    def _setup_local_mode(self):
        """Setup local-only mode (zero cost)."""
        self.router.set_local_only_mode(True)
        console.print("🏠 Local Mode: Zero cost, complete privacy", style="green")
    
    def _setup_smart_mode(self, gpu_providers: List[str] = None, threshold: int = 7):
        """Setup smart mode (optimal cost)."""
        self.router.set_local_only_mode(False)
        
        if gpu_providers:
            self.router.gpu_manager.set_provider_priority(gpu_providers)
        
        self.router.set_gpu_mode(True, threshold)
        console.print("🧠 Smart Mode: Optimal cost/performance balance", style="cyan")
    
    def _setup_cloud_mode(self, provider: str = "openai"):
        """Setup cloud mode (maximum power)."""
        self.router.set_local_only_mode(False)
        console.print(f"☁️  Cloud Mode: Using {provider} for maximum power", style="blue")
    
    def process(self, task: str, inputs: Dict[str, Any] = None) -> str:
        """
        Process a task with ZeroAI.
        
        Args:
            task: Task description
            inputs: Additional inputs for the task
            
        Returns:
            Task result
        """
        if inputs is None:
            inputs = {"query": task}
        
        return self.manager.process_task_with_smart_routing(task, inputs)
    
    def create_crew(self, crew_type: str = "research") -> Crew:
        """
        Create a ZeroAI crew.
        
        Args:
            crew_type: Type of crew ("research", "analysis", "custom")
            
        Returns:
            Configured crew
        """
        return self.manager.create_smart_crew(crew_type)
    
    def research(self, topic: str) -> str:
        """Quick research task."""
        return self.process(f"Research the topic: {topic}", {"topic": topic})
    
    def analyze(self, topic: str) -> str:
        """Quick analysis task."""
        task = f"Conduct comprehensive analysis of: {topic}"
        return self.process(task, {"topic": topic})
    
    def write(self, topic: str, style: str = "professional") -> str:
        """Quick writing task."""
        task = f"Write a {style} article about: {topic}"
        return self.process(task, {"topic": topic, "style": style})
    
    def chat(self, message: str) -> str:
        """Simple chat interaction."""
        return self.process(message, {"message": message})
    
    def set_mode(self, mode: str, **kwargs):
        """Change ZeroAI mode dynamically."""
        self.mode = mode
        
        if mode == "local":
            self._setup_local_mode()
        elif mode == "smart":
            self._setup_smart_mode(**kwargs)
        elif mode == "cloud":
            self._setup_cloud_mode(**kwargs)
    
    def get_status(self) -> Dict[str, Any]:
        """Get ZeroAI status."""
        status = self.router.get_status()
        status["zeroai_mode"] = self.mode
        status["framework"] = "ZeroAI v1.0.0"
        return status
    
    def show_status(self):
        """Display ZeroAI status."""
        console.print("\n💰 [bold]ZeroAI Status[/bold]")
        console.print("=" * 40)
        console.print(f"🎯 Mode: {self.mode.title()}")
        
        self.router.show_status()
    
    def enable_cost_optimization(self):
        """Enable aggressive cost optimization."""
        self.set_mode("smart", threshold=8)  # Higher threshold = more local processing
        console.print("💰 Cost optimization enabled", style="green")
    
    def enable_performance_mode(self):
        """Enable performance mode (may increase costs)."""
        self.set_mode("smart", threshold=5)  # Lower threshold = more GPU usage
        console.print("🚀 Performance mode enabled", style="yellow")
    
    def cleanup(self):
        """Clean up ZeroAI resources."""
        self.router.cleanup()
        console.print("🧹 ZeroAI resources cleaned up", style="blue")


# Convenience functions for quick access
def create_research_crew() -> Crew:
    """Create a ZeroAI research crew."""
    zero = ZeroAI()
    return zero.create_crew("research")

def create_analysis_crew() -> Crew:
    """Create a ZeroAI analysis crew."""
    zero = ZeroAI()
    return zero.create_crew("analysis")

def quick_research(topic: str) -> str:
    """Quick research with ZeroAI."""
    zero = ZeroAI()
    return zero.research(topic)

def quick_analysis(topic: str) -> str:
    """Quick analysis with ZeroAI."""
    zero = ZeroAI()
    return zero.analyze(topic)