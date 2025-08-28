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

    def execute_crew(self, category: str, query: str) -> str:
        """
        Creates and executes a specialized crew, returning the final result.
        """
        inputs = self.inputs.copy()
        inputs['topic'] = query
        inputs['category'] = category

        try:
            # Check for "auto" category and run classifier
            if category == "auto":
                category = self._classify_task(inputs)
                if not category:
                    return "Auto-classification failed."

            # Create and execute the specialized crew
            crew = self._create_specialized_crew(category, inputs)
            crew_output = crew.kickoff()
            return crew_output.result
        except Exception as e:
            console.print(f"❌ Error during specialized crew execution: {e}", style="red")
            return f"Failed to execute {category} crew: {e}"

    def _classify_task(self, inputs: Dict[str, Any]) -> Optional[str]:
        """
        Helper method to run the classification crew and return the category.
        """
        classifier_agent = create_classifier_agent(self.llm_instance)
        classifier_task = Task(
            description=f"""
            Classify the following user inquiry into one of these categories: 'math', 'coding', 'research', or 'general'.
            Inquiry: {inputs.get('topic')}.
            Provide only the category name as your output.
            """,
            agent=classifier_agent,
            expected_output="A single word representing the category: math, coding, research, or general.",
        )

        classifier_crew = Crew(
            agents=[classifier_agent],
            tasks=[classifier_task],
            verbose=config.agents.verbose,
        )

        try:
            classification_result = classifier_crew.kickoff()

            # Correct FIX: Access the result from the first item in the tasks_output list
            if classification_result.tasks_output and isinstance(classification_result.tasks_output, list) and classification_result.tasks_output[0]:
                return classification_result.tasks_output[0].result.strip().lower()
            else:
                raise Exception("Classification crew did not produce a valid output.")
        except Exception as e:
            console.print(f"❌ Classification failed: {e}", style="red")
            return None

    def _create_specialized_crew(self, category: str, inputs: Dict[str, Any]) -> Crew:
        console.print("📦 Creating a specialized crew for category: [bold yellow]{}[/bold yellow]".format(category),
                      style="blue")

        # Prevent delegation tools from recursively creating customer service crews.
        if category == "customer_service":
            raise ValueError("Recursive call to create_customer_service_crew detected. This is not allowed.")

        if category == "research":
            return self.create_research_crew(inputs)
        elif category == "analysis":
            return self.create_analysis_crew(inputs)
        elif category == "coding":
            return create_coding_crew(self.llm_instance, inputs)
        elif category == "math":
            return create_math_crew(self.llm_instance, inputs)
        elif category == "tech_support":
            return create_tech_support_crew(self.llm_instance, inputs)
        else:
            # Fallback to general crew creation if category is not recognized
            console.print(f"⚠️ Category '{category}' not recognized, defaulting to general purpose.", style="yellow")
            return self.create_customer_service_crew_hierarchical(self.llm_instance, inputs)

    def create_crew_for_category(self, inputs: Dict[str, Any]) -> Crew:
        category = inputs.get('category', 'auto')

        if category == "auto":
            category = self._classify_task(inputs)
            if not category:
                # Crash as requested if auto-classification fails
                raise Exception("Auto-classification failed. Crashing.")

        console.print(f"Manual category selected: [bold yellow]{category}[/bold yellow]", style="blue")

        # Route to the correct crew based on the classification result or user input
        console.print("📦 Creating a crew for category: [bold yellow]{}[/bold yellow]".format(category), style="blue")

        if category == "math":
            return create_math_crew(self.llm_instance, inputs)
        elif category == "coding":
            return create_coding_crew(self.llm_instance, inputs)
        elif category == "research":
            # Direct to the research crew if explicitly classified
            return self.create_research_crew(inputs)
        elif category == "tech_support":
            return create_tech_support_crew(self.llm_instance, inputs)
        else:
            # For general or unrecognized inquiries, route to the customer service crew
            return self.create_customer_service_crew_hierarchical(self.llm_instance, inputs)

    def create_customer_service_crew_hierarchical(self, llm: Ollama, inputs: Dict[str, Any]) -> Crew:
        customer_service_agent = create_customer_service_agent(llm, inputs)

        # For general tasks, it can still delegate to research or other specialized sub-crews if needed
        manager_tools = [
            ResearchDelegationTool(crew_manager=self, inputs=inputs),
        ]

        customer_service_task = Task(
            description=f"""
            Analyze the customer inquiry: {inputs.get('topic')}.
            If the inquiry requires research, use the 'Research Delegation Tool' to get the information.
            Otherwise, answer the inquiry directly based on internal knowledge or the context provided.
            **CRITICAL:** If any delegation fails or a specialist agent cannot provide a satisfactory response, you **must** fall back to providing a simple, direct answer to the customer yourself.
            """,
            agent=customer_service_agent,
            tools=manager_tools,
            expected_output="A polite and direct final answer to the customer's query."
        )

        all_agents = [customer_service_agent]

        return Crew(
            agents=all_agents,
            tasks=[customer_service_task],
            process=Process.hierarchical,
            manager_llm=llm,
            verbose=config.agents.verbose
        )

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
