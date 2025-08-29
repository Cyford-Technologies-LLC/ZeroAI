import logging
from typing import Dict, Any, Optional, List
from crewai import Agent, Task, Crew, Process, CrewOutput, TaskOutput
from rich.console import Console
from pydantic import BaseModel, Field
import warnings

from langchain_community.llms.ollama import Ollama

# --- Assuming these modules exist based on your imports ---
from config import config
from agents.base_agents import create_researcher, create_writer, create_analyst
from tasks.base_tasks import create_research_task, create_writing_task, create_analysis_task
from distributed_router import DistributedRouter
from crews.classifier.agents import create_classifier_agent
from crews.coding.crew import create_coding_crew
from crews.math.crew import create_math_crew
from crews.tech_support.crew import create_tech_support_crew
from crews.customer_service.tools import DelegatingMathTool, ResearchDelegationTool

# --- Import ALL specialized agents for Hierarchical Process ---
from crews.math.agents import create_mathematician_agent
from crews.coding.agents import create_coding_developer_agent, create_qa_engineer_agent
from crews.tech_support.agents import create_tech_support_agent
from crews.customer_service.agents import create_customer_service_agent


# --- Needed for CrewOutput token_usage compatibility ---
class UsageMetrics(BaseModel):
    total_tokens: Optional[int] = 0
    prompt_tokens: Optional[int] = 0
    completion_tokens: Optional[int] = 0
    successful_requests: Optional[int] = 0


console = Console()
logger = logging.getLogger(__name__)


# --- Plausible definitions for missing agent and task creators ---
def create_research_crew(router: DistributedRouter, inputs: Dict[str, Any]) -> Crew:
    researcher = create_researcher(router, inputs)
    writer = create_writer(router, inputs)
    research_task = create_research_task(inputs, researcher)
    writing_task = create_writing_task(inputs, writer)
    return Crew(
        agents=[researcher, writer],
        tasks=[research_task, writing_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=True
    )


def create_analysis_crew(router: DistributedRouter, inputs: Dict[str, Any]) -> Crew:
    analyst = create_analyst(router, inputs)
    analysis_task = create_analysis_task(inputs, analyst)
    return Crew(
        agents=[analyst],
        tasks=[analysis_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=True
    )


# --- The core AICrewManager class, now complete ---
class AICrewManager:
    """Manages AI crew creation and execution with a robust fallback."""

    def __init__(self, distributed_router_instance: DistributedRouter, **kwargs):
        # FIX: Ensure the router instance is of the correct type at initialization
        if not isinstance(distributed_router_instance, DistributedRouter):
            raise TypeError(
                f"Expected router to be a DistributedRouter instance, but got {type(distributed_router_instance)}")

        self.router = distributed_router_instance
        self.inputs = kwargs.get('inputs', {})
        self.category = self.inputs.get('category', 'general')
        self.task_description = self.inputs.get('topic', '')
        self.llm_instance = None

        print(f"DEBUG: AICrewManager initialized with task_description: '{self.task_description}'")
        print(f"DEBUG: AICrewManager initialized with category: '{self.category}'")
        logging.info(f"AICrewManager.__init__: self.inputs type={type(self.inputs)}, content={self.inputs}")

    def execute_crew(self, router: DistributedRouter, inputs: Dict[str, Any]) -> CrewOutput:
        """Executes the appropriate crew based on the category."""

        # --- Compatibility Layer to handle string inputs from older API calls ---
        if isinstance(inputs, str):
            logging.warning(f"Received string input from API, converting to dictionary: {inputs}")
            inputs = {"topic": inputs, "category": "auto"}

        # Check if inputs is a dictionary before proceeding (stricter type check)
        if not isinstance(inputs, dict):
            logging.error(f"Received non-dictionary inputs of type {type(inputs)}: {inputs}")
            raise TypeError(f"Expected 'inputs' to be a dictionary, but received type: {type(inputs)}. "
                            f"Received content: {inputs}")

        # --- End Compatibility Layer ---

        # FIX: Ensure self.router is not a string before proceeding
        if not isinstance(self.router, DistributedRouter):
            logging.error(f"Router has been corrupted: type is {type(self.router)}")
            raise TypeError("Router instance is corrupted. Expected DistributedRouter.")

        logging.info(f"AICrewManager.execute_crew: inputs type={type(inputs)}, content={inputs}")
        self.router = router
        self.inputs = inputs
        category = inputs.get('category', 'auto')

        if category == "auto":
            category = self._classify_task(inputs)
            if not category:
                raise Exception("Auto-classification failed.")

        crew = self.create_crew_for_category(inputs)

        try:
            with warnings.catch_warnings():
                warnings.simplefilter("ignore", DeprecationWarning)
                result = crew.kickoff()
                return result
        except Exception as e:
            console.print(f"❌ Error during crew execution AI : {e}", style="red")
            return CrewOutput(tasks_output=[], raw=f"Error: {e}", token_usage=UsageMetrics())

    def _classify_task(self, inputs: Dict[str, Any]) -> Optional[str]:
        """
        Helper method to run the classification crew and return the category.
        """
        logging.info(f"AICrewManager._classify_task: inputs type={type(inputs)}, content={inputs}")

        # FIX: Add a safeguard check for router instance
        if not isinstance(self.router, DistributedRouter):
            console.print(
                "⚠️ Failed to get optimal LLM for classifier via router: 'self.router' is not a DistributedRouter instance.",
                style="yellow")
            return "general"

        try:
            classifier_agent = create_classifier_agent(self.router, inputs)
        except ValueError as e:
            console.print(f"❌ Failed to create classifier agent: {e}", style="red")
            return "general"

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
            if classification_result and classification_result.tasks_output:
                last_task_output = classification_result.tasks_output[-1]
                if isinstance(last_task_output, TaskOutput) and last_task_output.raw:
                    category = last_task_output.raw.strip().lower()
                    if category in ['math', 'coding', 'research', 'general']:
                        console.print(f"✅ Classified category: [bold yellow]{category}[/bold yellow]", style="green")
                        return category
            console.print("❌ Classification crew did not produce a valid output. Falling back to 'general'.",
                          style="red")
            return "general"
        except Exception as e:
            console.print(f"❌ Classification failed with an exception: {e}", style="red")
            return "general"

    def _create_specialized_crew(self, category: str, inputs: Dict[str, Any]) -> Crew:
        """Helper method to create specialized crews based on category."""
        logging.info(f"AICrewManager._create_specialized_crew: inputs type={type(inputs)}, content={inputs}")
        console.print(f"📦 Creating a specialized crew for category: [bold yellow]{category}[/bold yellow]",
                      style="blue")

        if category == "research":
            return create_research_crew(self.router, inputs)
        elif category == "analysis":
            return create_analysis_crew(self.router, inputs)
        elif category == "coding":
            return create_coding_crew(self.router, inputs)
        elif category == "math":
            return create_math_crew(self.router, inputs)
        elif category == "tech_support":
            return create_tech_support_crew(self.router, inputs)
        elif category == "general":
            return create_research_crew(self.router, inputs)
        else:
            raise ValueError(f"Unknown category: {category}")

    def create_crew_for_category(self, inputs: Dict[str, Any]) -> Crew:
        """Main method for creating the top-level crew."""
        logging.info(f"AICrewManager.create_crew_for_category: inputs type={type(inputs)}, content={inputs}")
        category = inputs.get('category', self.category)
        console.print(f"📦 Creating a crew for category: [bold yellow]{category}[/bold yellow]", style="blue")

        if category == "customer_service":
            specialist_agents = [
                create_mathematician_agent(self.router, inputs),
                create_tech_support_agent(self.router, inputs),
                create_coding_developer_agent(self.router, inputs),
                create_researcher(self.router, inputs)
            ]
            return self.create_customer_service_crew_hierarchical(self.router, inputs, specialist_agents)
        elif category == "auto":
            # NOTE: Auto-classification is now handled in `execute_crew`
            return self._create_specialized_crew("general", inputs)
        else:
            console.print(f"⚠️  Category not recognized, defaulting to general crew for category: {category}",
                          style="yellow")
            return self._create_specialized_crew(category, inputs)

    def create_customer_service_crew_hierarchical(self, router: DistributedRouter, inputs: Dict[str, Any],
                                                  specialist_agents: List[Agent]) -> Crew:
        manager_llm = router.get_llm_for_role('manager')
        if not manager_llm:
            raise ValueError("Failed to get LLM for manager agent.")

        manager_agent = create_customer_service_agent(router, inputs)

        manager_task = Task(
            description=f"Manage the customer service inquiry related to: {inputs.get('topic')}",
            agent=manager_agent,
            expected_output="A final answer to the customer's inquiry.",
            context=[Task(description="Get help from the appropriate specialist.", agent=agent) for agent in
                     specialist_agents]
        )

        return Crew(
            agents=[manager_agent] + specialist_agents,
            tasks=[manager_task],
            process=Process.hierarchical,
            manager_llm=manager_llm,
            verbose=config.agents.verbose,
            full_output=True
        )
