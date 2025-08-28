# /opt/ZeroAI/src/ai_crew.py

import logging
from typing import Dict, Any, Optional
from crewai import Agent, Task, Crew, Process  # 3. Import crewai first
from crewai.tools import BaseTool # Import BaseTool from crewai
from rich.console import Console
from rich.progress import Progress, SpinnerColumn, TextColumn

from langchain_community.llms.ollama import Ollama

from config import config
from agents.base_agents import create_researcher, create_writer, create_analyst
from tasks.base_tasks import create_research_task, create_writing_task, create_analysis_task
from providers.cloud_providers import CloudProviderManager

console = Console()
logger = logging.getLogger(__name__)

# --- New functions for customer service and delegation ---

# Define a placeholder function to simulate a delegation tool.
def technical_support_tool_function(query: str):
    """
    Simulates delegating a query to a technical support crew.
    In a real system, this would trigger another crew or external service.
    """
    return f"Delegated to Technical Support for inquiry: {query}"

# Define the Tool for delegation.
tech_support_tool = BaseTool(
    name="Technical Support Delegation Tool",
    func=technical_support_tool_function,
    description="Tool to delegate technical support queries."
)

def create_customer_service_agent(llm, inputs: Dict[str, Any]) -> Agent:
    return Agent(
        role="Customer Service Representative",
        goal="Handle customer inquiries, answer questions, and delegate complex issues.",
        backstory=(
            "You are a friendly and efficient customer service representative. "
            "Your job is to understand the customer's request and provide a solution "
            "or delegate it to the appropriate specialized crew if needed. "
            "You always start by greeting the customer and confirming their request."
        ),
        llm=llm,
        tools=[tech_support_tool],  # The agent can now use this tool.
        verbose=True,
        allow_delegation=True
    )

def create_customer_service_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    return Task(
        description=f"Process the following customer inquiry: {inputs.get('topic')}",
        agent=agent,
        expected_output="A polite and helpful response that addresses the customer's query. "
                        "If the query requires specialized knowledge, the response should "
                        "indicate that it is being delegated to the correct team."
    )

def create_customer_service_crew(llm, inputs: Dict[str, Any]) -> Crew:
    customer_service_agent = create_customer_service_agent(llm, inputs)
    customer_service_task = create_customer_service_task(customer_service_agent, inputs)

    return Crew(
        agents=[customer_service_agent],
        tasks=[customer_service_task],
        process=Process.sequential,
        verbose=config.agents.verbose
    )

# --- End new functions ---

class AICrewManager:
    """Manages AI crew creation and execution."""

    def __init__(self, distributed_router_instance, **kwargs):
        self.router = distributed_router_instance
        self.category = kwargs.pop('category', 'general')
        self.task_description = kwargs.get('topic', kwargs.get('task', ''))
        self.inputs = kwargs

        # FIX: Move category mapping to the top of the __init__ method
        if self.category == "chat" and not self.task_description:
            self.task_description = "llama3.2:latest"
        elif self.category == "coding" and not self.task_description:
            self.task_description = "codellama:13b"
        elif self.category == "customer_service" and not self.task_description:
            # You may want a different default model for this category
            self.task_description = "llama3.2:latest"


        # Debug prints now correctly show the resolved task_description
        print(f"DEBUG: AICrewManager initialized with task_description: '{self.task_description}'")
        print(f"DEBUG: AICrewManager initialized with category: '{self.category}'")

        # The router call is now inside a try block
        try:
            self.base_url, self.peer_name, self.model_name = self.router.get_optimal_endpoint_and_model(self.task_description)
            print(f"DEBUG: Router returned URL: {self.base_url}, Peer: {self.peer_name}, Model: {self.model_name}")
        except Exception as e:
            print(f"❌ Error during router call in AICrewManager: {e}")
            raise

        # FIX: Prepend 'ollama/' to the model name for LiteLLM
        prefixed_model_name = f"ollama/{self.model_name}"

        self.max_tokens = kwargs.get('max_tokens', config.model.max_tokens)
        self.provider = "local"
        self.llm_config = {
            "model": prefixed_model_name,
            "base_url": self.base_url,
            "temperature": config.model.temperature
        }

        console.print(f"✅ Preparing LLM config for Ollama: [bold yellow]{self.llm_config['model']}[/bold yellow] at [bold green]{self.base_url}[/bold green]", style="blue")

    def create_crew_for_category(self, inputs: Dict[str, Any]) -> Crew:
        console.print(f"📦 Creating a crew for category: [bold yellow]{self.category}[/bold yellow]", style="blue")
        if self.category == "research":
            return self.create_research_crew(inputs)
        elif self.category == "analysis":
            return self.create_analysis_crew(inputs)
        elif self.category == "coding":
            return self.create_coding_crew(inputs)
        elif self.category == "customer_service":
            return self.create_customer_service_crew(inputs)
        else:
            console.print("⚠️  Category not recognized, defaulting to general crew.", style="yellow")
            return self.create_research_crew(inputs)

    def create_research_crew(self, inputs: Dict[str, Any]) -> Crew:
        # Pass the LLM config directly when creating agents
        llm_instance = Ollama(**self.llm_config)
        researcher = create_researcher(llm_instance, inputs)
        writer = create_writer(llm_instance, inputs)
        research_task = create_research_task(researcher, inputs)
        writing_task = create_writing_task(writer, inputs, context=[research_task])
        return Crew(
            agents=[researcher, writer],
            tasks=[research_task, writing_task],
            verbose=config.agents.verbose
        )

    def create_analysis_crew(self, inputs: Dict[str, Any]) -> Crew:
        # Pass the LLM config directly when creating agents
        llm_instance = Ollama(**self.llm_config)
        researcher = create_researcher(llm_instance, inputs)
        analyst = create_analyst(llm_instance, inputs)
        writer = create_writer(llm_instance, inputs)
        research_task = create_research_task(researcher, inputs)
        analysis_task = create_analysis_task(analyst, inputs)
        writing_task = create_writing_task(writer, inputs)
        return Crew(
            agents=[researcher, analyst, writer],
            tasks=[research_task, analysis_task, writing_task],
            verbose=config.agents.verbose
        )

    def create_coding_crew(self, inputs: Dict[str, Any]) -> Crew:
        # Pass the LLM config directly when creating agents
        llm_instance = Ollama(**self.llm_config)
        coder = Agent(
            role='Senior Software Developer',
            goal=f'Write clean, efficient, and well-documented code for the task: "{inputs.get("topic")}". Context: "{inputs.get("context")}".',
            backstory='A seasoned developer with expertise in multiple programming languages.',
            verbose=True,
            llm=llm_instance,
        )
        qa_engineer = Agent(
            role='Quality Assurance Engineer',
            goal='Review the generated code for correctness, bugs, and best practices.',
            backstory='A meticulous QA engineer who ensures all code is of the highest quality.',
            verbose=True,
            llm=llm_instance,
        )
        coding_task = Task(
            description=f"Generate code to fulfill the request: {inputs.get('topic')}. Context: {inputs.get('context')}.",
            expected_output='A well-commented code snippet',
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

    def create_customer_service_crew(self, inputs: Dict[str, Any]) -> Crew:
        llm_instance = Ollama(**self.llm_config)
        customer_service_agent = create_customer_service_agent(llm_instance, inputs)
        customer_service_task = create_customer_service_task(customer_service_agent, inputs)

        return Crew(
            agents=[customer_service_agent],
            tasks=[customer_service_task],
            process=Process.sequential,
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

