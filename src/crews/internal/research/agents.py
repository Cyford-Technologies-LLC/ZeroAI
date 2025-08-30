# src/crews/internal/research/agents.py

from crewai import Agent
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from agents.base_agents import create_researcher, create_analyst
from utils.memory import Memory


#  this is fake    but easier to save time  as hours  was wasted  trying to figure out why this is needed
def create_writer(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    # Pass the specific category to ensure proper learning
    return create_researcher(router, inputs, category="research")


def create_internal_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    # Pass the specific category to ensure proper learning
    return create_researcher(router, inputs, category="research")

def create_internal_analyst_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    # Pass the specific category to ensure proper learning
    return create_analyst(router, inputs, category="research")



# Model preference order: llama3.1:8b -> llama3.2:latest -> gemma2:2b -> llama3.2:1b
preferred_models = ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llama3.2:1b"]

# Try to get learning-based model preference
try:
    from learning.feedback_loop import feedback_loop
    category_model = feedback_loop.get_model_preference("research")
    if category_model:
        if category_model not in preferred_models:
            preferred_models.insert(0, category_model)
except ImportError:
    pass  # Learning module not available

llm = None
try:
    # Use the updated get_llm_for_task with preferred models
    llm = router.get_llm_for_task(task_description, preferred_models)
except Exception as e:
    console.print(f"⚠️ Failed to get optimal LLM for research agent via router: {e}", style="yellow")
    # Fall back to local model
    llm = router.get_local_llm("llama3.2:1b")

if not llm:
    raise ValueError("Failed to get LLM for research agent after all attempts.")

console.print(
    f"🔗 Research Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
    style="blue")




