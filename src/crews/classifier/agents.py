from crewai import Agent
from langchain_community.llms.ollama import Ollama
from config import config
from distributed_router import DistributedRouter
from rich.console import Console

console = Console()

def create_classifier_agent(router: DistributedRouter) -> Agent:
    llm = router.get_llm_for_role('classifier')
    if not llm:
        llm = router.get_local_llm("llama3.2:1b")
    if not llm:
        raise ValueError("Failed to get LLM for classifier agent.")
    console.print(f"🔗 Classifier Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]", style="blue")
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
