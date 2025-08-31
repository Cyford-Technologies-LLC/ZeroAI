from crewai import Agent
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
# from agents.base_agents import create_researcher, create_analyst # <--- UNCOMMENTED THIS LINE
from rich.console import Console
console = Console()

def get_research_llm(router: DistributedRouter, category: str = "research"):
    """
    Selects the optimal LLM based on preferences and learning,
    with a fallback mechanism.
    """
    # Model preference order: llama3.1:8b -> llama3.2:latest -> gemma2:2b -> llama3.2:1b
    preferred_models = ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llama3.2:1b"]

    # Try to get learning-based model preference
    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference(category)
        if category_model:
            if category_model not in preferred_models:
                preferred_models.insert(0, category_model)
    except ImportError:
        pass  # Learning module not available

    llm = None
    try:
        # Use the updated get_llm_for_task with preferred models
        llm = router.get_llm_for_task(category, preferred_models)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for {category} agent via router: {e}", style="yellow")
        # Fall back to local model
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError(f"Failed to get LLM for {category} agent after all attempts.")

    console.print(
        f"🔗 {category.capitalize()} Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    return llm


def create_internal_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None) -> Agent:
    llm = get_research_llm(router, category="research")
    return create_researcher(router, inputs, category="research", llm=llm)

def create_internal_analyst_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None) -> Agent:
    llm = get_research_llm(router, category="research")
    return create_analyst(router, inputs, category="research", llm=llm)
