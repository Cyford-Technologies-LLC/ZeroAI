import logging
from typing import Dict, Any, Optional, List
from crewai import Agent, Task, Crew, Process, CrewOutput
from rich.console import Console
from rich.progress import Progress, SpinnerColumn, TextColumn, BarColumn, TimeRemainingColumn

from langchain_community.llms.ollama import Ollama

from config import config
from agents.base_agents import create_researcher, create_writer, create_analyst
from tasks.base_tasks import create_research_task, create_writing_task, create_analysis_task

# --- New Crew Imports ---
from crews.coding.crew import create_coding_crew
from crews.math.crew import create_math_crew
from crews.tech_support.crew import create_tech_support_crew
# from crews.customer_service.tasks import create_customer_service_task # Not used directly here
from crews.customer_service.tools import DelegatingMathTool, ResearchDelegationTool

# --- Import ALL specialist agents for Hierarchical Process ---
from crews.math.agents import create_mathematician_agent
from crews.coding.agents import create_coding_developer_agent, create_qa_engineer_agent
from crews.tech_support.agents import create_tech_support_agent
from crews.customer_service.agents import create_customer_service_agent

console = Console()
logger = logging.getLogger(__name__)

class AICrewManager:
    """Manages AI crew creation and execution with a robust fallback."""

    def __init__(self, distributed_router_instance, **kwargs):
        self.router = distributed_router_instance
        self.category = kwargs.pop('category', 'general')
        self.task_description = kwargs.get('topic', kwargs.get('task', ''))
        self.inputs = kwargs

        # Default model for each category if not specified
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

    def create_crew_for_category(self, category: str, inputs: Dict[str, Any]) -> Crew:
        console.print(f"📦 Creating a crew for category: [bold yellow]{category}[/bold yellow]", style="blue")
        if category == "research":
            return self.create_research_crew(inputs)
        elif category == "analysis":
            return self.create_analysis_crew(inputs)
        elif category == "coding":
            return create_coding_crew(self.llm_instance, inputs)
        elif category == "customer_service":
            specialist_agents = [
                create_mathematician_agent(self.llm_instance, inputs),
                create_tech_support_agent(self.llm_instance, inputs),
                create_coding_developer_agent(self.llm_instance, inputs),
                create_researcher(self.llm_instance, inputs)
            ]
            return self.create_customer_service_crew_hierarchical(self.llm_instance, inputs, specialist_agents)
        elif category == "tech_support":
            return create_tech_support_crew(self.llm_instance, inputs)
        elif category == "math":
            return create_math_crew(self.llm_instance, inputs)
        else:
            console.print("⚠️  Category not recognized, defaulting to customer service crew.", style="yellow")
            specialist_agents = [
                create_mathematician_agent(self.llm_instance, inputs),
                create_tech_support_agent(self.llm_instance, inputs),
                create_coding_developer_agent(self.llm_instance, inputs),
                create_researcher(self.llm_instance, inputs)
            ]
            return self.create_customer_service_crew_hierarchical(self.llm_instance, inputs, specialist_agents)

    def create_customer_service_crew_hierarchical(self, llm: Ollama, inputs: Dict[str, Any], specialist_agents: List[Agent]) -> Crew:
        customer_service_agent = create_customer_service_agent(llm, inputs)

        # Instantiate delegation tools, passing the AICrewManager instance (self) and inputs
        manager_tools = [
            DelegatingMathTool(crew_manager=self, inputs=inputs),
            ResearchDelegationTool(crew_manager=self, inputs=inputs),
            # Add other delegating tools here
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
            tools=manager_tools, # Provide tools to the manager agent
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
        """Execute a crew with progress tracking and return full response, with a robust fallback."""
        console.print(f"🚀 Executing Crew for category: [bold yellow]{self.category}[/bold yellow]", style="bold blue")
        final_output = None
        try:
            with Progress(
                SpinnerColumn(),
                TextColumn("[progress.description]{task.description}"),
                BarColumn(),
                TimeRemainingColumn(),
                console=console
            ) as progress:
                task_id = progress.add_task(f"[yellow]Executing {self.category} crew...", total=None)
                result = crew.kickoff(inputs=inputs)
                progress.update(task_id, description=f"[green]Execution of {self.category} crew complete.", completed=1)
                final_output = result
        except Exception as e:
            console.print(f"❌ Execution of crew failed: {e}", style="bold red")
            # --- Fallback logic ---
            console.print("🔄 Initiating fallback to provide a simple response...", style="bold yellow")
            fallback_agent = Agent(
                role='Fallback Responder',
                goal='Provide a simple, direct, and polite answer to the customer query when other crews fail.',
                backstory="An AI that assists customers when specialized crews encounter problems.",
                llm=self.llm_instance,
                verbose=False
            )
            fallback_task = Task(
                description=f"Provide a simple, direct answer for the inquiry: {inputs.get('topic')}. A previous specialized crew failed to complete this task.",
                agent=fallback_agent,
                expected_output="A polite and simple fallback answer for the customer."
            )
            fallback_crew = Crew(
                agents=[fallback_agent],
                tasks=[fallback_task],
                verbose=False
            )
            final_output = fallback_crew.kickoff(inputs=inputs)

        return {"output": final_output}

