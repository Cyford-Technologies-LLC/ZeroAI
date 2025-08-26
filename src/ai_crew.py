# /opt/ZeroAI/src/ai_crew.py

import logging
from typing import Dict, Any, Optional
from crewai import Agent, Task, Crew, LLM
from rich.console import Console
from rich.progress import Progress, SpinnerColumn, TextColumn

import sys
import os
from pathlib import Path
# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent))

from config import config
from distributed_router import distributed_router
from agents.base_agents import create_researcher, create_writer, create_analyst
from tasks.base_tasks import create_research_task, create_writing_task, create_analysis_task
from providers.cloud_providers import CloudProviderManager

console = Console()
logger = logging.getLogger(__name__)


class AICrewManager:
    """Manages AI crew creation and execution."""

    def __init__(self, **kwargs):
        """Initialize the AI Crew Manager with category and task context."""
        self.category = kwargs.pop('category', 'general')
        self.task_description = kwargs.get('task', '')

        model_name = kwargs.pop('model_name', None)
        if not model_name:
            if any(word in self.task_description.lower() for word in ['code', 'php', 'python', 'javascript']) or self.category == 'coding':
                self.model_name = 'codellama:13b'
            else:
                self.model_name = 'llama3.2:1b'
        else:
            self.model_name = model_name

        self.provider = kwargs.pop('provider', 'local')
        # Store the server endpoint during initialization
        self.endpoint = None
        self.llm = self._setup_llm(**kwargs)

    def _setup_llm(self, **kwargs) -> LLM:
        """Setup LLM connection (local or cloud)."""
        try:
            if self.provider == "local":
                base_url, peer_name = distributed_router.get_optimal_endpoint(self.task_description, self.model_name)
                console.print(f"✅ BASEURL {base_url}", style="green")
                self.endpoint = base_url  # Store the base URL
                llm = LLM(
                    model=f"ollama/{self.model_name}",
                    base_url=base_url,
                    temperature=config.model.temperature,
                    max_tokens=config.model.max_tokens
                )
                console.print(f"✅ Connected to {self.model_name}", style="green")
            elif self.provider in ["openai", "anthropic", "azure", "google"]:
                # For cloud providers, store provider name and model for simplicity
                self.endpoint = self.provider
                if self.provider == "openai":
                    llm = CloudProviderManager.create_openai_llm(model=self.model_name, **kwargs)
                # ... (add other cloud provider logic) ...
                console.print(f"✅ Connected to {self.provider} {self.model_name}", style="green")
            else:
                raise ValueError(f"Unsupported provider: {self.provider}")

            return llm
        except Exception as e:
            console.print(f"❌ Failed to connect to {self.provider}: {e}", style="red")
            raise

    # ... (rest of the class remains the same) ...
    def create_crew_for_category(self, inputs: Dict[str, Any]) -> Crew:
        # ... (implementation remains the same) ...

    def execute_crew(self, crew: Crew, inputs: Dict[str, Any]) -> str:
        # ... (implementation remains the same) ...

    # Helper method to get the LLM details
    def get_llm_details(self) -> Dict[str, str]:
        return {
            "model_name": self.model_name,
            "provider": self.provider,
            "endpoint": self.endpoint
        }
