"""Smart AI Manager with intelligent provider routing."""

from typing import Dict, Any, Optional
from crewai import Crew
from rich.console import Console

from src.intelligent_router import IntelligentAIRouter
from src.agents.base_agents import create_researcher, create_writer, create_analyst
from src.tasks.base_tasks import create_research_task, create_writing_task, create_analysis_task


console = Console()


class SmartAIManager:
    """Intelligent AI manager that routes tasks based on complexity."""
    
    def __init__(self):
        self.router = IntelligentAIRouter()
        
    def create_smart_crew(self, crew_type: str = "research") -> Crew:
        """Create a crew that uses intelligent routing."""
        
        if crew_type == "research":
            return self._create_research_crew()
        elif crew_type == "analysis":
            return self._create_analysis_crew()
        else:
            return self._create_research_crew()
    
    def _create_research_crew(self) -> Crew:
        """Create research crew with smart routing."""
        # These will use the router to get appropriate LLMs
        researcher_llm = self.router.route_task("Research and gather information")
        writer_llm = self.router.route_task("Write comprehensive report")
        
        researcher = create_researcher(researcher_llm)
        writer = create_writer(writer_llm)
        
        research_task = create_research_task(researcher)
        writing_task = create_writing_task(writer)
        
        return Crew(
            agents=[researcher, writer],
            tasks=[research_task, writing_task],
            verbose=True
        )
    
    def _create_analysis_crew(self) -> Crew:
        """Create analysis crew with smart routing."""
        researcher_llm = self.router.route_task("Research basic information")
        analyst_llm = self.router.route_task("Perform complex data analysis and strategic insights")
        writer_llm = self.router.route_task("Write detailed analysis report")
        
        researcher = create_researcher(researcher_llm)
        analyst = create_analyst(analyst_llm)
        writer = create_writer(writer_llm)
        
        research_task = create_research_task(researcher)
        analysis_task = create_analysis_task(analyst)
        writing_task = create_writing_task(writer)
        
        return Crew(
            agents=[researcher, analyst, writer],
            tasks=[research_task, analysis_task, writing_task],
            verbose=True
        )
    
    def process_task_with_smart_routing(self, task_description: str, inputs: Dict[str, Any]) -> str:
        """Process a single task with intelligent routing."""
        
        # Route based on task description
        llm = self.router.route_task(task_description)
        
        # Create simple crew for this specific task
        from .agents.base_agents import create_custom_agent
        from .tasks.base_tasks import create_custom_task
        
        agent = create_custom_agent(
            role="Smart AI Assistant",
            goal="Complete the given task efficiently",
            backstory="Intelligent AI that adapts to task complexity",
            llm=llm
        )
        
        task = create_custom_task(
            description=task_description,
            agent=agent,
            expected_output="Complete and accurate response to the task"
        )
        
        crew = Crew(agents=[agent], tasks=[task], verbose=True)
        return crew.kickoff(inputs=inputs)
    
    # Configuration methods
    def enable_thunder_mode(self, auto_start: bool = True, threshold: int = 7):
        """Enable Thunder Compute with smart routing."""
        self.router.set_thunder_mode(True, auto_start, threshold)
        console.print("⚡ Thunder Compute enabled with smart routing", style="green")
    
    def disable_thunder_mode(self):
        """Disable Thunder Compute completely."""
        self.router.set_thunder_mode(False)
        console.print("❌ Thunder Compute disabled", style="red")
    
    def enable_local_only_mode(self):
        """Force all processing to local only."""
        self.router.set_local_only_mode(True)
        console.print("🏠 Local-only mode enabled - no cloud costs", style="blue")
    
    def disable_local_only_mode(self):
        """Allow cloud/Thunder processing."""
        self.router.set_local_only_mode(False)
        console.print("🌐 Cloud processing re-enabled", style="green")
    
    def set_complexity_threshold(self, threshold: int):
        """Set complexity threshold for Thunder auto-start (1-10)."""
        if 1 <= threshold <= 10:
            self.router.set_thunder_mode(
                self.router.thunder.is_enabled(),
                self.router.thunder.should_auto_start(),
                threshold
            )
            console.print(f"🎯 Complexity threshold set to {threshold}/10", style="cyan")
        else:
            console.print("❌ Threshold must be between 1-10", style="red")
    
    def get_status(self) -> Dict[str, Any]:
        """Get current AI manager status."""
        status = self.router.get_status()
        status["manager_type"] = "Smart AI Manager"
        return status
    
    def show_status(self):
        """Display current configuration status."""
        status = self.get_status()
        
        console.print("\n📊 [bold]Smart AI Manager Status[/bold]")
        console.print("=" * 40)
        
        if status["local_only_mode"]:
            console.print("🏠 Mode: Local Only (Budget Mode)", style="blue")
        elif status["thunder_enabled"]:
            if status["thunder_auto_start"]:
                console.print(f"⚡ Mode: Smart Thunder (Auto-start at {status['complexity_threshold']}/10)", style="green")
            else:
                console.print("🔧 Mode: Manual Thunder", style="yellow")
        else:
            console.print("🏠 Mode: Local Processing", style="blue")
        
        console.print(f"🎯 Complexity Threshold: {status['complexity_threshold']}/10")
        console.print(f"🚀 Thunder Auto-start: {status['thunder_auto_start']}")
        console.print(f"⚡ Thunder Enabled: {status['thunder_enabled']}")
        console.print(f"🏠 Local Only: {status['local_only_mode']}")


# Convenience functions for backward compatibility
def create_smart_research_crew() -> Crew:
    """Create a research crew with smart routing."""
    manager = SmartAIManager()
    return manager.create_smart_crew("research")

def create_smart_analysis_crew() -> Crew:
    """Create an analysis crew with smart routing."""
    manager = SmartAIManager()
    return manager.create_smart_crew("analysis")