"""Main AI Crew management system."""


try:
    __import__('pysqlite3')
    import sys
    sys.modules['sqlite3'] = sys.modules.pop('pysqlite3')
except ImportError:
    pass


import logging
from typing import List, Dict, Any, Optional
from crewai import Agent, Task, Crew, LLM
from rich.console import Console
from rich.progress import Progress, SpinnerColumn, TextColumn

import sys
import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from config import config
from distributed_router import distributed_router
from agents.base_agents import create_researcher, create_writer, create_analyst
from tasks.base_tasks import create_research_task, create_writing_task, create_analysis_task
from providers.cloud_providers import CloudProviderManager

console = Console()
logger = logging.getLogger(__name__)


class AICrewManager:
    """Manages AI crew creation and execution."""
    
    def __init__(self, model_name: Optional[str] = None, provider: str = "local", **kwargs):
        """Initialize the AI Crew Manager."""
        # Use distributed router to select optimal model if not specified
        if not model_name and 'task' in kwargs:
            # Simple model selection based on task
            task = kwargs['task'].lower()
            if any(word in task for word in ['code', 'php', 'python', 'javascript']):
                self.model_name = 'codellama:13b'
            else:
                # self.model_name = 'llama3.1:8b'
                self.model_name = 'llama3.2:1b'
        else:
            self.model_name = model_name or config.model.name
        self.provider = provider
        self.llm = self._setup_llm(**kwargs)
        
    def _setup_llm(self, **kwargs) -> LLM:
        """Setup LLM connection (local or cloud)."""
        try:
            if self.provider == "local":
                # Use distributed routing to find best peer
                task_description = kwargs.get('task', '')
                base_url, peer_name = distributed_router.get_optimal_endpoint(task_description, self.model_name)
                console.print(f"✅ BASEURL {base_url}", style="green")
                llm = LLM(
                    model=f"ollama/{self.model_name}",
                    base_url=base_url,
                    temperature=config.model.temperature,
                    max_tokens=config.model.max_tokens
                )
                console.print(f"✅ Connected to {self.model_name}", style="green")
            elif self.provider == "openai":
                llm = CloudProviderManager.create_openai_llm(
                    model=self.model_name,
                    **kwargs
                )
                console.print(f"✅ Connected to OpenAI {self.model_name}", style="green")
            elif self.provider == "anthropic":
                llm = CloudProviderManager.create_anthropic_llm(
                    model=self.model_name,
                    **kwargs
                )
                console.print(f"✅ Connected to Anthropic {self.model_name}", style="green")
            elif self.provider == "azure":
                llm = CloudProviderManager.create_azure_llm(
                    model=self.model_name,
                    **kwargs
                )
                console.print(f"✅ Connected to Azure {self.model_name}", style="green")
            elif self.provider == "google":
                llm = CloudProviderManager.create_google_llm(
                    model=self.model_name,
                    **kwargs
                )
                console.print(f"✅ Connected to Google {self.model_name}", style="green")
            else:
                raise ValueError(f"Unsupported provider: {self.provider}")
            
            return llm
        except Exception as e:
            console.print(f"❌ Failed to connect to {self.provider}: {e}", style="red")
            raise
    
    def create_research_crew(self) -> Crew:
        """Create a research-focused crew."""
        researcher = create_researcher(self.llm)
        writer = create_writer(self.llm)
        
        research_task = create_research_task(researcher)
        writing_task = create_writing_task(writer)
        
        return Crew(
            agents=[researcher, writer],
            tasks=[research_task, writing_task],
            verbose=config.agents.verbose
        )
    
    def create_analysis_crew(self) -> Crew:
        """Create an analysis-focused crew."""
        researcher = create_researcher(self.llm)
        analyst = create_analyst(self.llm)
        writer = create_writer(self.llm)
        
        research_task = create_research_task(researcher)
        analysis_task = create_analysis_task(analyst)
        writing_task = create_writing_task(writer)
        
        return Crew(
            agents=[researcher, analyst, writer],
            tasks=[research_task, analysis_task, writing_task],
            verbose=config.agents.verbose
        )
    
    def execute_crew(self, crew: Crew, inputs: Dict[str, Any]) -> str:
        """Execute a crew with progress tracking."""
        with Progress(
            SpinnerColumn(),
            TextColumn("[progress.description]{task.description}"),
            console=console,
        ) as progress:
            task = progress.add_task("Executing AI crew...", total=None)
            
            try:
                result = crew.kickoff(inputs=inputs)
                progress.update(task, description="✅ Crew execution completed!")
                return result
            except Exception as e:
                progress.update(task, description=f"❌ Crew execution failed: {e}")
                raise


def create_research_crew(model_name: Optional[str] = None, provider: str = "local", **kwargs) -> Crew:
    """Convenience function to create a research crew."""
    manager = AICrewManager(model_name, provider, **kwargs)
    return manager.create_research_crew()


def create_analysis_crew(model_name: Optional[str] = None, provider: str = "local", **kwargs) -> Crew:
    """Convenience function to create an analysis crew."""
    manager = AICrewManager(model_name, provider, **kwargs)
    return manager.create_analysis_crew()