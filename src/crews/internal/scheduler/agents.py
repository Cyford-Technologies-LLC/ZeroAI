# src/crews/internal/scheduler/agents.py

from crewai import Agent
from src.utils.knowledge_utils import get_common_knowledgefrom langchain_ollama import OllamaLLM
from src.crews.internal.tools.scheduling_tool import SchedulingTool
from src.config import config
from typing import Dict, Any, List, Optional
from src.distributed_router import DistributedRouter
from src.utils.shared_knowledge import get_shared_context_for_agent

from src.utils.tool_initializer import get_universal_tools


# --- Helper function to get LLM ---
def get_scheduler_llm(router: DistributedRouter, category: str = "scheduling") -> Any:
    """
    Selects the optimal LLM for scheduling tasks with fallback.
    """
    preferred_models = ["llama3.1:8b", "llama3.2:latest", "gemma2:2b"]

    llm = None
    try:
        task_description = f"Perform {category} tasks."
        llm = router.get_llm_for_task(task_description)
    except Exception as e:
        # Fallback to a local model using the centralized config
        llm = OllamaLLM(model=config.model.name, base_url=config.model.base_url)

    if not llm:
        raise ValueError(f"Failed to get LLM for {category} agent after all attempts.")

    return llm


# --- End of Helper function ---

def create_scheduler_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None):
    """
    Creates a Scheduler agent.
    """
    llm = get_scheduler_llm(router)

    # Use tools passed from the main crew manager
    all_tools = get_universal_tools(inputs, initial_tools=tools)
    
    project_location = inputs.get("project_id")
    repository = inputs.get("repository")
    common_knowledge = get_common_knowledge(project_location, repository)




    return Agent(
        role="Scheduler",
        goal="Schedule events and appointments based on requests from the team manager. or project manager",
        backstory=f"An expert in calendar management, proficient at scheduling, organizing, and managing events and appointments efficiently.\n\n{get_shared_context_for_agent('Scheduler')}\n\nAll responses are signed off with 'Scheduler'",
        tools=all_tools,
        resources=[],
        knowledge_sources=knowledge_sources,
        llm=llm,
        allow_delegation=False,
        verbose=config.agents.verbose
    )
