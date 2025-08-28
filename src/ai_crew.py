# Path: src/ai_crew.py

import logging
from typing import Dict, Any, Optional, List
from crewai import Agent, Task, Crew, Process, CrewOutput, TaskOutput
from rich.console import Console
import warnings

from langchain_community.llms.ollama import Ollama
from langchain_community import __version__ as langchain_community_version
from langchain.tools import BaseTool
from pydantic import BaseModel, Field

from config import config
from agents.base_agents import create_researcher, create_writer, create_analyst
from tasks.base_tasks import create_research_task, create_writing_task, create_analysis_task

# --- New Crew Imports ---
from crews.coding.crew import create_coding_crew
from crews.math.crew import create_math_crew
from crews.tech_support.crew import create_tech_support_crew

# --- Import ALL specialist agents for Hierarchical Process ---
from crews.math.agents import create_mathematician_agent
from crews.coding.agents import create_coding_developer_agent, create_qa_engineer_agent
from crews.tech_support.agents import create_tech_support_agent
from crews.customer_service.agents import create_customer_service_agent
from crews.customer_service.tools import DelegatingMathTool, ResearchDelegationTool

console = Console()
logger = logging.getLogger(__name__)


# Define a placeholder class for UsageMetrics since it's removed in new CrewAI versions
class UsageMetrics:
    def __init__(self, total_tokens=0, prompt_tokens=0, completion_tokens=0, successful_requests=0):
        self.total_tokens = total_tokens
        self.prompt_tokens = prompt_tokens
        self.completion_tokens = completion_tokens
        self.successful_requests = successful_requests


# --- Input Schema for Delegating Tools ---
class CrewDelegationInput(BaseModel):
    query: str = Field(description="The user's query or the task to delegate.")


# --- Task Classifier Agent ---
def create_classifier_agent(llm: Ollama) -> Agent:
    return Agent(
        role='Task Classifier',
        goal='Accurately classify the user query into categories: math, coding, research, or general.',
        backstory=(
            "As a Task Classifier, your primary role is to analyze the incoming user query "
            "and determine the most suitable crew to handle it. You must be highly accurate "
            "to ensure the correct crew is activated for the job."
        ),
        llm=llm,
        verbose=config.agents.verbose,
        allow_delegation=False,
    )


class AICrewManager:
    """Manages AI crew creation and execution with a robust fallback."""

    def __init__(self, distributed_router_instance, **kwargs):
        self.router = distributed_router_instance
        self.category = kwargs.pop('category', 'general')
        self.task_description = kwargs.get('topic', kwargs.get('task', ''))
        self.inputs = kwargs

        if self.category == "chat" and not self.task_description:
            self.task_description = "llama3.2:latest"
        elif self.category == "coding" and not self.task_description:
            self.task_description = "codellama:13b"
        elif self.category == "customer_service" and not self.task_description:
            self.task_description = "llama3.2:latest"
        elif self.category == "tech_support" and not self.task_description:
            self.task_description = "llama3.2:latest"
        elif self.category == "math" and not self.task_description:
            self.task_description = "llama3.2:latest"

        print(f"DEBUG: AICrewManager initialized with task_description: '{self.task_description}'")
        print(f"DEBUG: AICrewManager initialized with category: '{self.category}'")

        try:
            self.base_url, self.peer_name, self.model_name = self.router.get_optimal_endpoint_and_model(
                self.task_description)
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

        with warnings.catch_warnings():
            warnings.simplefilter("ignore", DeprecationWarning)
            self.llm_instance = Ollama(**self.llm_config)

        console.print(
            f"✅ Preparing LLM config for Ollama: [bold yellow]{self.llm_config['model']}[/bold yellow] at [bold green]{self.base_url}[/bold green]",
            style="blue")

    def execute_crew(self, category: str, query: str) -> CrewOutput:
        """
        Creates and executes a specialized crew, returning the full CrewOutput object.
        """
        inputs = self.inputs.copy()
        inputs['topic'] = query
        inputs['category'] = category

        try:
            # Check for "auto" category and run classifier
            if category == "auto":
                classified_category = self._classify_task(inputs)
                if not classified_category:
                    raise Exception("Auto-classification failed.")
                # FIX: Use the classified category for the rest of the method
                category = classified_category
                # FIX: Update the inputs dictionary for create_crew_for_category
                inputs['category'] = classified_category

            # Create and execute the specialized crew
            crew = self.create_crew_for_category(inputs)
            crew_output = crew.kickoff()
            return crew_output
        except Exception as e:
            console.print(f"❌ Error during specialized crew execution: {e}", style="red")

            # Create a mock agent role for the TaskOutput object.
            mock_agent = "Error Handler"

            error_output = CrewOutput(
                raw=f"Failed to execute {category} crew: {e}",
                tasks_output=[
                    TaskOutput(
                        raw=f"Failed to execute: {e}",
                        description="Error in execution",
                        agent=mock_agent
                    )
                ],
                pydantic=None,
                json_dict=None,
                token_usage=None
            )
            return error_output

    def _classify_task(self, inputs: Dict[str, Any]) -> Optional[str]:
        """
        Helper method to run the classification crew and return the category.
        """
        classifier_agent = create_classifier_agent(self.llm_instance)
        classifier_task = Task(
            description=f"""
            Classify the following user inquiry into one of these categories: 'math', 'coding', 'research', or 'general'.
            Inquiry: {inputs.get('topic')}.
            Provide ONLY the single word category name as your final output, do not include any other text or formatting.
            """,
            agent=classifier_agent,
            expected_output="A single word representing the category: math, coding, research, or general.",
        )

        classifier_crew = Crew(
            agents=[classifier_agent],
            tasks=[classifier_task],
            verbose=config.agents.verbose,
            full_output=True
        )

        try:
            classification_result = classifier_crew.kickoff()
            console.print("[bold cyan]--- Classifier Crew Output Dump ---[/bold cyan]")
            console.print(classification_result)
            console.print("[bold cyan]----------------------------[/bold cyan]")

            if classification_result and classification_result.tasks_output:
                last_task_output = classification_result.tasks_output[-1]
                if last_task_output and last_task_output.raw:
                    category = last_task_output.raw.strip().lower()
                    if category in ['math', 'coding', 'research', 'general']:
                        console.print(f"✅ Classified category: [bold yellow]{category}[/bold yellow]", style="green")
                        return category
                    else:
                        console.print(f"❌ Invalid category '{category}' returned. Falling back to 'general'.",
                                      style="red")

            console.print("❌ Classification crew did not produce a valid output. Falling back to 'general'.",
                          style="red")
            return "general"
        except Exception as e:
            console.print(f"❌ Classification failed with an exception: {e}", style="red")
            return "general"

    def create_crew_for_category(self, inputs: Dict[str, Any], full_output: bool = True) -> Crew:
        category = inputs.get("category", self.category)
        console.print(f"📦 Creating a specialized crew for category: [bold yellow]{category}[/bold yellow]",
                      style="blue")

        if category == "customer_service":
            raise ValueError("Recursive call to create_customer_service_crew detected. This is not allowed.")

        if category == "research":
            return self.create_research_crew(inputs, full_output=full_output)
        elif category == "analysis":
            return self.create_analysis_crew(inputs, full_output=full_output)
        elif category == "coding":
            return create_coding_crew(self.llm_instance, inputs, full_output=full_output)
        elif category == "math":
            return create_math_crew(self.llm_instance, inputs, full_output=full_output)
        elif category == "tech_support":
            return create_tech_support_crew(self.llm_instance, inputs, full_output=full_output)
        else:
            console.print(f"⚠️ Category '{category}' not recognized, falling back to general research crew.",
                          style="yellow")
            return self.create_research_crew(inputs, full_output=full_output)

    def create_research_crew(self, inputs: Dict[str, Any], full_output: bool = True) -> Crew:
        researcher = create_researcher(self.llm_instance, inputs)
        writer = create_writer(self.llm_instance, inputs)

        research_task = create_research_task(researcher, inputs)
        writing_task = create_writing_task(writer, [research_task], inputs)

        return Crew(
            agents=[researcher, writer],
            tasks=[research_task, writing_task],
            verbose=config.agents.verbose,
            process=Process.sequential,
            full_output=full_output,
        )

    def create_analysis_crew(self, inputs: Dict[str, Any], full_output: bool = True) -> Crew:
        researcher = create_researcher(self.llm_instance, inputs)
        analyst = create_analyst(self.llm_instance, inputs)

        research_task = create_research_task(researcher, inputs)
        analysis_task = create_analysis_task(analyst, [research_task], inputs)

        return Crew(
            agents=[researcher, analyst],
            tasks=[research_task, analysis_task],
            verbose=config.agents.verbose,
            process=Process.sequential,
            full_output=full_output,
        )

