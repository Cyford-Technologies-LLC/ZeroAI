# ai_crew.py

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
from crews.customer_service.tools import ResearchDelegationTool  # FIX: Removed DelegatingMathTool

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
    if not researcher or not isinstance(researcher, Agent):
        raise ValueError("Failed to create researcher agent.")
    if not writer or not isinstance(writer, Agent):
        raise ValueError("Failed to create writer agent.")
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
        if not isinstance(distributed_router_instance, DistributedRouter):
            logging.error(
                f"FATAL: Router is not a DistributedRouter instance. Type: {type(distributed_router_instance)}, Value: {distributed_router_instance}")
            raise TypeError(
                f"Expected router to be a DistributedRouter instance, but got {type(distributed_router_instance)}")
        self.router = distributed_router_instance
        self.inputs = kwargs.get('inputs', {})
        self.category = self.inputs.get('category', 'general')
        self.task_description = self.inputs.get('topic', '')
        self.llm_instance = None
        logging.info(f"AICrewManager.__init__: self.inputs type={type(self.inputs)}, content={self.inputs}")
        logging.info(f"AICrewManager.__init__: self.router instance stored. Type: {type(self.router)}")

    def execute_crew(self, router: DistributedRouter, inputs: Dict[str, Any]) -> CrewOutput:
        """Executes the appropriate crew based on the category."""
        if isinstance(inputs, str):
            logging.warning(f"Received string input from API, converting to dictionary: {inputs}")
            inputs = {"topic": inputs, "category": "auto"}
        if not isinstance(inputs, dict):
            logging.error(f"Received non-dictionary inputs of type {type(inputs)}: {inputs}")
            raise TypeError(f"Expected 'inputs' to be a dictionary, but received type: {type(inputs)}. "
                            f"Received content: {inputs}")
        logging.info(f"AICrewManager.execute_crew: inputs type={type(inputs)}, content={inputs}")
        logging.info(
            f"AICrewManager.execute_crew: router instance check before classification. Type: {type(self.router)}")
        self.inputs = inputs
        category = inputs.get('category', 'auto')
        if category == "auto":
            category = self._classify_task(inputs)
            if not category:
                raise Exception("Auto-classification failed.")
            inputs['category'] = category

        crew = self.create_crew_for_category(self.router, inputs)

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
        logging.info(
            f"AICrewManager._classify_task: router instance check before agent creation. Type: {type(self.router)}")
        if not isinstance(self.router, DistributedRouter):
            console.print(
                "⚠️ Failed to get optimal LLM for classifier via router: 'self.router' is not a DistributedRouter instance.",
                style="yellow")
            return "general"
        try:
            # FIX: Pass router and inputs to create_classifier_agent
            classifier_agent = create_classifier_agent(self.router, inputs)
        except ValueError as e:
            console.print(f"❌ Failed to create classifier agent: {e}", style="red")
            return "general"
        classifier_task = Task(
            description=f"""
            Classify the following user inquiry into one of these categories: 'math', 'coding', 'research', 'customer_service', or 'general'.
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
                if isinstance(last_task_output, TaskOutput) and last_task_output.output:  # FIX: Use .output
                    return last_task_output.output.strip().lower()  # FIX: Use .output
            return "general"
        except Exception as e:
            console.print(f"❌ Error during classification: {e}", style="red")
            return "general"

    def create_crew_for_category(self, router: DistributedRouter, inputs: Dict[str, Any]) -> Crew:
        """Create a specialized crew based on the category, or default to general."""
        category = inputs.get('category', 'general')

        if category in ["general", "customer_service"]:
            return self.create_customer_service_crew(router, inputs)
        elif category == "math":
            return create_math_crew(router, inputs)
        elif category == "coding":
            return create_coding_crew(router, inputs)
        elif category == "tech_support":
            return create_tech_support_crew(router, inputs)
        elif category == "research":
            return create_research_crew(router, inputs)
        elif category == "analysis":
            return create_analysis_crew(router, inputs)
        else:
            raise ValueError(f"Unknown crew category: {category}")

    def create_customer_service_crew(self, router: DistributedRouter, inputs: Dict[str, Any]) -> Crew:
        # FIX: Pass 'self' to the agent creation function
        agent = self.create_customer_service_agent(router, inputs)
        task = Task(
            description=inputs.get("topic", "Handle a general customer inquiry."),
            agent=agent
        )
        return Crew(
            agents=[agent],
            tasks=[task],
            process=Process.sequential,
            verbose=True
        )

    def create_customer_service_agent(self, router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
        task_description = "Handle customer inquiries, answer questions, and delegate complex issues to the correct specialized crew."
        llm = router.get_llm_for_task(task_description)
        # FIX: Remove DelegatingMathTool from tools
        tools = [
            ResearchDelegationTool(crew_manager=self, inputs=inputs)
        ]
        return Agent(
            role="Customer Service Representative",
            goal="Handle customer inquiries, answer questions, and delegate complex issues to the correct specialized crew.",
            backstory="You are an AI customer service representative designed to handle inquiries.",
            llm=llm,
            tools=tools,
            verbose=True,
            allow_delegation=False
        )

