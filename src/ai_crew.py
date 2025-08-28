import logging
from typing import Dict, Any, Optional, List
from crewai import Agent, Task, Crew, Process, CrewOutput, TaskOutput
from rich.console import Console
from pydantic import BaseModel, Field

from langchain_community.llms.ollama import Ollama

from config import config
from agents.base_agents import create_researcher, create_writer, create_analyst
from tasks.base_tasks import create_research_task, create_writing_task, create_analysis_task

# --- Import all crew creation functions ---
from crews.coding.crew import create_coding_crew
from crews.math.crew import create_math_crew
from crews.tech_support.crew import create_tech_support_crew
from crews.classifier.crew import create_classifier_crew as create_classifier_crew_internal

# --- Import all specialized agent creation functions for Hierarchical Process ---
from crews.math.agents import create_mathematician_agent
from crews.coding.agents import create_coding_developer_agent, create_qa_engineer_agent
from crews.tech_support.agents import create_tech_support_agent
from crews.customer_service.agents import create_customer_service_agent
from crews.customer_service.tools import DelegatingMathTool, ResearchDelegationTool

# --- Import classifier agent creation function ---
from crews.classifier.agents import create_classifier_agent


# Needed for CrewOutput token_usage compatibility
class UsageMetrics(BaseModel):
    total_tokens: Optional[int] = 0
    prompt_tokens: Optional[int] = 0
    completion_tokens: Optional[int] = 0
    successful_requests: Optional[int] = 0


console = Console()
logger = logging.getLogger(__name__)


class AICrewManager:
    """Manages AI crew creation and execution with a robust fallback."""

    def __init__(self, distributed_router_instance, **kwargs):
        self.router = distributed_router_instance
        self.category = kwargs.pop('category', 'general')
        self.task_description = kwargs.get('topic', kwargs.get('task', ''))
        self.inputs = kwargs

        print(f"DEBUG: AICrewManager initialized with task_description: '{self.task_description}'")
        print(f"DEBUG: AICrewManager initialized with category: '{self.category}'")

    def _classify_task(self, inputs: Dict[str, Any]) -> Optional[str]:
        """
        Helper method to run the classification crew and return the category.
        """
        try:
            # Pass the router and inputs to the classifier agent creation function
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
        console.print(f"📦 Creating a specialized crew for category: [bold yellow]{category}[/bold yellow]",
                      style="blue")

        # Pass the router instance to the specific crew creation functions
        if category == "research":
            return self.create_research_crew(self.router, inputs)
        elif category == "analysis":
            return self.create_analysis_crew(self.router, inputs)
        elif category == "coding":
            return create_coding_crew(self.router, inputs)
        elif category == "math":
            return create_math_crew(self.router, inputs)
        elif category == "tech_support":
            return create_tech_support_crew(self.router, inputs)
        elif category == "general":
            return self.create_research_crew(self.router, inputs)
        else:
            raise ValueError(f"Unknown category: {category}")

    def create_crew_for_category(self, inputs: Dict[str, Any]) -> Crew:
        """Main method for creating the top-level crew."""
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
            classified_category = self._classify_task(inputs)
            if not classified_category:
                raise Exception("Auto-classification failed.")
            return self._create_specialized_crew(classified_category, inputs)
        else:
            console.print(f"⚠️  Category not recognized, defaulting to general crew for category: {category}",
                          style="yellow")
            return self._create_specialized_crew("general", inputs)

    def create_customer_service_crew_hierarchical(self, router: DistributedRouter, inputs: Dict[str, Any],
                                                  specialist_agents: List[Agent]) -> Crew:
        manager_llm = router.get_llm_for_role('manager')
        if not manager_llm:
            raise ValueError("Failed to get LLM for manager agent.")

        customer_service_agent = create_customer_service_agent(router, inputs)

        manager_tools = [
            DelegatingMathTool(crew_manager=self, inputs=inputs),
            ResearchDelegationTool(crew_manager=self, inputs=inputs),
        ]

        customer_service_task = Task(
            description=f"""
            Analyze the customer inquiry: {inputs.get('topic')}.
            If the inquiry is a math problem, use the 'Delegating Math Tool' to solve it.
            If it requires research, use the 'Research Delegation Tool' to get the information.
            Otherwise, answer the inquiry directly.

            **CRITICAL:** If any delegation fails or a specialist agent cannot provide a satisfactory response, you **must** fall back to providing a simple, direct answer to the customer yourself.
            """,
            agent=customer_service_agent,
            tools=manager_tools,
            expected_output="A polite and direct final answer to the customer's query."
        )

        all_agents = [customer_service_agent] + specialist_agents

        return Crew(
            agents=all_agents,
            tasks=[customer_service_task],
            process=Process.hierarchical,
            manager_llm=manager_llm,
            verbose=config.agents.verbose
        )

    def create_research_crew(self, router: DistributedRouter, inputs: Dict[str, Any]) -> Crew:
        researcher = create_researcher(router, inputs)
        writer = create_writer(router, inputs)
        research_task = create_research_task(researcher, inputs)
        writing_task = create_writing_task(writer, inputs, context=[research_task])
        return Crew(
            agents=[researcher, writer],
            tasks=[research_task, writing_task],
            verbose=config.agents.verbose
        )

    def create_analysis_crew(self, router: DistributedRouter, inputs: Dict[str, Any]) -> Crew:
        researcher = create_researcher(router, inputs)
        analyst = create_analyst(router, inputs)
        writer = create_writer(router, inputs)
        research_task = create_research_task(researcher, inputs)
        analysis_task = create_analysis_task(analyst, inputs)
        writing_task = create_writing_task(writer, inputs)
        return Crew(
            agents=[researcher, analyst, writer],
            tasks=[research_task, analysis_task, writing_task],
            verbose=config.agents.verbose
        )

