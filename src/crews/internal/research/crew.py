# src/crews/internal/research/crew.py

from crewai import Crew, Process
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from src.crews.internal.research.agents  import create_internal_researcher_agent, create_internal_analyst_agent
from src.crews.internal.research.tasks import internal_research_task, internal_analysis_task
def get_research_crew(router, tools, project_config, use_new_memory=False):
    """
    Wrapper function to create the documentation crew using the existing create_documentation_crew function.

    Args:
        router: The DistributedRouter instance for model selection
        tools: List of tools to use
        project_config: Project configuration
        use_new_memory: Whether to use new memory instances for agents

    Returns:
        A Crew instance for documentation tasks
    """
    # Prepare inputs based on the project_config
    inputs = {
        "working_dir": project_config.get("crewai_settings", {}).get("working_directory", "/tmp"),
        "project_name": project_config.get("project", {}).get("name", "unknown"),
        # Add any other inputs needed by the documentation crew
    }


def create_research_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    researcher_agent = create_internal_researcher_agent(router, inputs)
    analyst_agent = create_internal_analyst_agent(router, inputs)

    tasks = [
        internal_research_task(researcher_agent, inputs),
        internal_analysis_task(analyst_agent, inputs)
    ]

    return Crew(
        agents=[researcher_agent, analyst_agent],
        tasks=tasks,
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )
