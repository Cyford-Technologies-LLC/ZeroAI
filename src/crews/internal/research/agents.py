# src/crews/internal/research/agents.py

from crewai import Agent
from typing import Dict, Any, List, Optional
from distributed_router import DistributedRouter
from config import config
from rich.console import Console

# Assuming Memory is imported and configured
from src.utils.memory import Memory

console = Console()

def get_research_llm(router: DistributedRouter, category: str = "research", preferred_models: Optional[List] = None) -> Any:
    """
    Selects the optimal LLM based on preferences and learning,
    with a fallback mechanism.
    """
    if preferred_models is None:
        preferred_models = ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llama3.2:1b"]

    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference(category)
        if category_model and category_model not in preferred_models:
            preferred_models.insert(0, category_model)
    except ImportError:
        pass

    llm = None
    try:
        task_description = f"Perform {category} tasks."
        llm = router.get_llm_for_task(task_description)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for {category} agent via router: {e}", style="yellow")
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError(f"Failed to get LLM for {category} agent after all attempts.")

    console.print(
        f"🔗 {category.capitalize()} Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    return llm

def create_internal_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None, coworkers: Optional[List] = None) -> Agent:
    """Create a specialized researcher agent."""
    llm = get_research_llm(router, category="research")
    return Agent(
        role="Internal Researcher",
        name="Internal Research Specialist",
        goal="Gather information on internal project details.",
        backstory="An expert at internal research, finding and documenting all project-specific information.",
        llm=llm,
        tools=tools,
        verbose=config.agents.verbose,
        coworkers=coworkers if coworkers is not None else [],
        allow_delegation=False,
    )

def create_internal_analyst_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None, coworkers: Optional[List] = None) -> Agent:
    """Create a specialized analyst agent."""
    llm = get_research_llm(router, category="research")
    return Agent(
        role="Internal Analyst",
        name="Internal Analyst",
        goal="Analyze research results and provide insights.",
        backstory="A detail-oriented analyst who synthesizes information from internal research to provide insights.",
        llm=llm,
        tools=tools,
        coworkers=coworkers if coworkers is not None else [],
        verbose=config.agents.verbose,
        allow_delegation=False,
    )
