# /opt/ZeroAI/src/ai_crew.py

import logging
from typing import Dict, Any, Optional
from crewai import Agent, Task, Crew, Process, CrewOutput
from rich.console import Console
from rich.progress import Progress, SpinnerColumn, TextColumn

# Fix: Use explicit imports for LLM providers
from langchain_community.chat_models import ChatOllama
from langchain_community.llms.ollama import Ollama
from crewai.llm import LLM as CrewAILLM  # Use crewai's LLM for compatibility

from config import config
from agents.base_agents import create_researcher, create_writer, create_analyst
from tasks.base_tasks import create_research_task, create_writing_task, create_analysis_task
from providers.cloud_providers import CloudProviderManager

console = Console()
logger = logging.getLogger(__name__)

class AICrewManager:
    """Manages AI crew creation and execution."""

    def __init__(self, distributed_router_instance, **kwargs):
        self.router = distributed_router_instance
        self.category = kwargs.pop('category', 'general')
        self.task_description = kwargs.get('topic', kwargs.get('task', ''))

        # New: Store inputs to propagate to LLM setup
        self.inputs = kwargs

        base_url, peer_name, model_name = self.router.get_optimal_endpoint_and_model(self.task_description)

        self.model_name = model_name
        self.endpoint = base_url
        self.peer_name = peer_name
        self.max_tokens = kwargs.get('max_tokens', config.model.max_tokens)
        self.provider = "local"
        self.llm = self._setup_llm(**kwargs)

    def _setup_llm(self, **kwargs) -> CrewAILLM:
        try:
            if self.provider == "local":
                console.print(f"✅ Connecting to Ollama at BASEURL: [bold green]{self.endpoint}[/bold green] for model: [bold yellow]{self.model_name}[/bold yellow]")

                # Fix: Use Ollama directly from langchain for native support
                # This explicitly sets the model and base_url separately as required
                ollama_llm_instance = Ollama(
                    model=self.model_name,
                    base_url=self.endpoint,
                    temperature=config.model.temperature,
                )

                # Fix: Wrap the LangChain LLM with CrewAILLM for compatibility
                llm = CrewAILLM(ollama_llm_instance)

                console.print(f"✅ Connected to {self.model_name} on {self.peer_name}", style="green")
            elif self.provider in ["openai", "anthropic", "azure", "google"]:
                self.endpoint = self.provider
                if self.provider == "openai":
                    llm = CloudProviderManager.create_openai_llm(model=self.model_name, **kwargs)
                console.print(f"✅ Connected to {self.provider} {self.model_name}", style="green")
            else:
                raise ValueError(f"Unsupported provider: {self.provider}")
            return llm
        except Exception as e:
            console.print(f"❌ Failed to connect to {self.provider} at {self.endpoint} for model {self.model_name}: {e}", style="red")
            raise

    def create_crew_for_category(self, inputs: Dict[str, Any]) -> Crew:
        console.print(f"📦 Creating a crew for category: [bold yellow]{self.category}[/bold yellow]", style="blue")
        if self.category == "research":
            return self.create_research_crew(inputs)
        elif self.category == "analysis":
            return self.create_analysis_crew(inputs)
        elif self.category == "coding":
            return self.create_coding_crew(inputs)
        else:
            console.print("⚠️  Category not recognized, defaulting to general crew.", style="yellow")
            return self.create_research_crew(inputs)

    def create_research_crew(self, inputs: Dict[str, Any]) -> Crew:
        researcher = create_researcher(self.llm, inputs)
        writer = create_writer(self.llm, inputs)
        research_task = create_research_task(researcher, inputs)
        writing_task = create_writing_task(writer, inputs, context=[research_task])
        return Crew(
            agents=[researcher, writer],
            tasks=[research_task, writing_task],
            verbose=config.agents.verbose
        )

    def create_analysis_crew(self, inputs: Dict[str, Any]) -> Crew:
        researcher = create_researcher(self.llm, inputs)
        analyst = create_analyst(self.llm, inputs)
        writer = create_writer(self.llm, inputs)
        research_task = create_research_task(researcher, inputs)
        analysis_task = create_analysis_task(analyst, inputs)
        writing_task = create_writing_task(writer, inputs)
        return Crew(
            agents=[researcher, analyst, writer],
            tasks=[research_task, analysis_task, writing_task],
            verbose=config.agents.verbose
        )

    def create_coding_crew(self, inputs: Dict[str, Any]) -> Crew:
        coder = Agent(
            role='Senior Software Developer',
            goal=f'Write clean, efficient, and well-documented code for the task: "{inputs.get("topic")}". Context: "{inputs.get("context")}".',
            backstory='A seasoned developer with expertise in multiple programming languages.',
            verbose=True,
            llm=self.llm,
        )
        qa_engineer = Agent(
            role='Quality Assurance Engineer',
            goal='Review the generated code for correctness, bugs, and best practices.',
            backstory='A meticulous QA engineer who ensures all code is of the highest quality.',
            verbose=True,
            llm=self.llm,
        )
        coding_task = Task(
            description=f"Generate code to fulfill the request: {inputs.get('topic')}. Context: {inputs.get('context')}.",
            expected_output='A well-commented code snippet that solves the problem.',
            agent=coder
        )
        review_task = Task(
            description="Review the code generated by the developer.",
            expected_output='A quality assurance report highlighting potential issues and improvements.',
            agent=qa_engineer
        )
        return Crew(
            agents=[coder, qa_engineer],
            tasks=[coding_task, review_task],
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

                result_text = None
                if self.category == 'coding':
                    for task_output in crew_output_object.tasks_outputs:
                        if "code" in task_output.description.lower():
                            result_text = task_output.output
                            break
                    if result_text is None:
                        result_text = crew_output_object.raw
                else:
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
            "model_name": self.model_name,
            "provider": self.provider,
            "endpoint": self.endpoint
        }
