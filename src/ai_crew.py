import logging
from typing import Dict, Any, Optional, List
from crewai import Agent, Task, Crew, Process, CrewOutput, TaskOutput
from rich.console import Console
from rich.progress import Progress, SpinnerColumn, TextColumn, BarColumn, TimeRemainingColumn
from pydantic import BaseModel, Field

from langchain_community.llms.ollama import Ollama

from config import config
from agents.base_agents import create_researcher, create_writer, create_analyst
from tasks.base_tasks import create_research_task, create_writing_task, create_analysis_task

# --- New Crew Imports ---
from crews.coding.crew import create_coding_crew
from crews.math.crew import create_math_crew
from crews.tech_support.crew import create_tech_support_crew
from crews.customer_service.tools import DelegatingMathTool, ResearchDelegationTool

# --- Import ALL specialist agents for Hierarchical Process ---
from crews.math.agents import create_mathematician_agent
from crews.coding.agents import create_coding_developer_agent, create_qa_engineer_agent
from crews.tech_support.agents import create_tech_support_agent
from crews.customer_service.agents import create_customer_service_agent


# Needed for CrewOutput token_usage compatibility
class UsageMetrics(BaseModel):
    total_tokens: Optional[int] = 0
    prompt_tokens: Optional[int] = 0
    completion_tokens: Optional[int] = 0
    successful_requests: Optional[int] = 0


console = Console()
logger = logging.getLogger(__name__)


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
        elif self.category == "auto" and not self.task_description:
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

        self.max_tokens = kwargs.get('max_tokens', config.model.max_tokens)
        self.llm_config = {
            "model": self.model_name,
            "base_url": self.base_url,
            "temperature": config.model.temperature
        }
        self.llm_instance = Ollama(**self.llm_config)

        console.print(
            f"✅ Preparing LLM config for Ollama: [bold yellow]{self.llm_config['model']}[/bold yellow] at [bold green]{self.base_url}[/bold green]",
            style="blue")

    def _classify_task(self, inputs: Dict[str, Any]) -> Optional[str]:
        """
        Helper method to run the classification crew and return the category.
        """
        # **FIX:** Ensure the create_classifier_agent receives the correct LLM instance.
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

    def _create_specialized_crew(self, category: str, inputs: Dict[str, Any]) -> Crew:
        """Helper method to create specialized crews based on category."""
        console.print(f"📦 Creating a specialized crew for category: [bold yellow]{category}[/bold yellow]",
                      style="blue")
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
        elif category == "general":
            return self.create_research_crew(inputs)
        else:
            raise ValueError(f"Unknown category: {category}")

    def create_crew_for_category(self, inputs: Dict[str, Any]) -> Crew:
        """Main method for creating the top-level crew."""
        category = inputs.get('category', self.category)
        console.print(f"📦 Creating a crew for category: [bold yellow]{category}[/bold yellow]", style="blue")

        if category == "customer_service":
            specialist_agents = [
                create_mathematician_agent(self.llm_instance, inputs),
                create_tech_support_agent(self.llm_instance, inputs),
                create_coding_developer_agent(self.llm_instance, inputs),
                create_researcher(self.llm_instance, inputs)
            ]
            return self.create_customer_service_crew_hierarchical(self.llm_instance, inputs, specialist_agents)
        elif category == "auto":
            classified_category = self._classify_task(inputs)
            if not classified_category:
                raise Exception("Auto-classification failed.")
            return self._create_specialized_crew(classified_category, inputs)
        else:
            console.print(f"⚠️  Category not recognized, defaulting to customer service crew for category: {category}",
                          style="yellow")
            specialist_agents = [
                create_mathematician_agent(self.llm_instance, inputs),
                create_tech_support_agent(self.llm_instance, inputs),
                create_coding_developer_agent(self.llm_instance, inputs),
                create_researcher(self.llm_instance, inputs)
            ]
            return self.create_customer_service_crew_hierarchical(self.llm_instance, inputs, specialist_agents)

    def create_customer_service_crew_hierarchical(self, llm: Ollama, inputs: Dict[str, Any],
                                                  specialist_agents: List[Agent]) -> Crew:
        customer_service_agent = create_customer_service_agent(llm, inputs)

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
            manager_llm=llm,
            verbose=config.agents.verbose
        )

    def create_research_crew(self, inputs: Dict[str, Any]) -> Crew:
        researcher = create_researcher(self.llm_instance, inputs)
        writer = create_writer(self.llm_instance, inputs, topic=inputs.get('topic'))
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
        writer = create_writer(self.llm_instance, inputs, topic=inputs.get('topic'))
        research_task = create_research_task(researcher, inputs)
        analysis_task = create_analysis_task(analyst, inputs)
        writing_task = create_writing_task(writer, inputs)
        return Crew(
            agents=[researcher, analyst, writer],
            tasks=[research_task, analysis_task, writing_task],
            verbose=config.agents.verbose
        )

    def execute_crew(self, category: str, query: str) -> CrewOutput:
        inputs = self.inputs.copy()
        inputs['topic'] = query
        inputs['category'] = category

        try:
            if not self.llm_instance:
                raise ValueError("LLM instance is not initialized. Check router configuration.")

            # **FIX**: Handle 'auto' category here and call _create_specialized_crew with the classified category.
            if category == "auto":
                classified_category = self._classify_task(inputs)
                if not classified_category:
                    raise Exception("Auto-classification failed.")
                crew = self._create_specialized_crew(classified_category, inputs)
            else:
                crew = self._create_specialized_crew(category, inputs)

            with Progress(
                    SpinnerColumn(),
                    TextColumn("[progress.description]{task.description}"),
                    BarColumn(bar_width=None),
                    TimeRemainingColumn(),
                    console=console
            ) as progress:
                task = progress.add_task(description=f"Executing crew for '{category}'...", total=None)
                crew_output = crew.kickoff()
                progress.update(task, completed=True, description=f"Execution for '{category}' complete.")
            return crew_output
        except Exception as e:
            console.print(f"❌ Error during crew execution: {e}", style="red")
            return CrewOutput(
                raw=f"Error during crew execution: {e}",
                tasks_output=[
                    TaskOutput(
                        raw=f"Execution failed due to: {e}",
                        description="Error during crew execution.",
                        agent="Error Handler"
                    )
                ],
                pydantic=None,
                json_dict=None,
                token_usage=UsageMetrics()
            )
