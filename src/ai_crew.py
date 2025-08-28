import logging
from typing import Dict, Any, Optional
from crewai import Agent, Task, Crew, Process
from rich.console import Console
from rich.progress import Progress, SpinnerColumn, TextColumn

from langchain_community.llms.ollama import Ollama

from config import config
from agents.base_agents import create_researcher, create_writer, create_analyst
from tasks.base_tasks import create_research_task, create_writing_task, create_analysis_task
from providers.cloud_providers import CloudProviderManager

# --- New Crew Imports ---
from crews.customer_service.crew import create_customer_service_crew
from crews.coding.crew import create_coding_crew
# Assuming you also create a tech_support crew file
from crews.tech_support.crew import create_tech_support_crew

console = Console()
logger = logging.getLogger(__name__)

class AICrewManager:
    """Manages AI crew creation and execution."""

    def __init__(self, distributed_router_instance, **kwargs):
        self.router = distributed_router_instance
        self.category = kwargs.pop('category', 'general')
        self.task_description = kwargs.get('topic', kwargs.get('task', ''))
        self.inputs = kwargs

        # Move category mapping to the top of the __init__ method
        if self.category == "chat" and not self.task_description:
            self.task_description = "llama3.2:latest"
        elif self.category == "coding" and not self.task_description:
            self.task_description = "codellama:13b"
        elif self.category == "customer_service" and not self.task_description:
            self.task_description = "llama3.2:latest"
        elif self.category == "tech_support" and not self.task_description:
            self.task_description = "llama3.2:latest"

        print(f"DEBUG: AICrewManager initialized with task_description: '{self.task_description}'")
        print(f"DEBUG: AICrewManager initialized with category: '{self.category}'")

        try:
            self.base_url, self.peer_name, self.model_name = self.router.get_optimal_endpoint_and_model(self.task_description)
            print(f"DEBUG: Router returned URL: {self.base_url}, Peer: {self.peer_name}, Model: {self.model_name}")
        except Exception as e:
            print(f"❌ Error during router call in AICrewManager: {e}")
            raise

        prefixed_model_name = f"ollama/{self.model_name}"

        self.max_tokens = kwargs.get('max_tokens', config.model.max_tokens)
        self.provider = "local"
        self.llm_config = {
            "model": prefixed_model_name,
            "base_url": self.base_url,
            "temperature": config.model.temperature
        }
        self.llm_instance = Ollama(**self.llm_config)

        console.print(f"✅ Preparing LLM config for Ollama: [bold yellow]{self.llm_config['model']}[/bold yellow] at [bold green]{self.base_url}[/bold green]", style="blue")

    def create_crew_for_category(self, inputs: Dict[str, Any]) -> Crew:
        console.print(f"📦 Creating a crew for category: [bold yellow]{self.category}[/bold yellow]", style="blue")
        if self.category == "research":
            return self.create_research_crew(inputs)
        elif self.category == "analysis":
            return self.create_analysis_crew(inputs)
        elif self.category == "coding":
            # Call the imported function from the new module
            return create_coding_crew(self.llm_instance, inputs)
        elif self.category == "customer_service":
            # Call the imported function from the new module
            return create_customer_service_crew(self.llm_instance, inputs)
        elif self.category == "tech_support":
            # Call the imported function from the new module
            return create_tech_support_crew(self.llm_instance, inputs)
        else:
            console.print("⚠️  Category not recognized, defaulting to general crew.", style="yellow")
            return self.create_research_crew(inputs)

    def create_research_crew(self, inputs: Dict[str, Any]) -> Crew:
        researcher = create_researcher(self.llm_instance, inputs)
        writer = create_writer(self.llm_instance, inputs)
        research_task = create_research_task(researcher, inputs)
        writing_task = create_writing_task(writer, inputs, context=[research_task])
        return Crew(
            agents=[researcher, writer],
            tasks=[research_task, writing_task],
            verbose=config.agents.verbose
        )

    def create_analysis_crew(self, inputs: Dict[str, Any]) -> Crew:
        researcher = create_researcher(self.llm_instance, inputs)
        analyst = create_analyst(self.llm_instance, inputs)
        writer = create_writer(self.llm_instance, inputs)
        research_task = create_research_task(researcher, inputs)
        analysis_task = create_analysis_task(analyst, inputs)
        writing_task = create_writing_task(writer, inputs)
        return Crew(
            agents=[researcher, analyst, writer],
            tasks=[research_task, analysis_task, writing_task],
            verbose=config.agents.verbose
        )

    def execute_crew(self, crew: Crew, inputs: Dict[str, Any]) -> Dict[str, Any]:
        """Execute a crew with progress tracking and return full response."""
        with Progress(
            SpinnerColumn(),
            TextColumn("[progress.description]{task.description}"),
            console=console,
        ) as progress:
            task = progress.add_task("Executing AI crew...", total=None)

            try:
                crew_output_object = crew.kickoff(inputs=inputs)
                result_text = crew_output_object.raw
                progress.update(task, description="✅ Crew execution completed!")
                return {
                    "result": result_text,
                    "llm_details": self.get_llm_details()
                }
            except Exception as e:
                progress.update(task, description=f"❌ Crew execution failed: {e}")
                logger.error("Crew execution failed", exc_info=True)
                raise

    def get_llm_details(self) -> Dict[str, str]:
        return {
            "model_name": self.llm_config['model'],
            "provider": self.provider,
            "endpoint": self.base_url
        }
