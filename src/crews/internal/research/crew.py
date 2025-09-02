# src/crews/internal/research/crew.py
from crewai import Crew, Process
from typing import Dict, Any, List
from distributed_router import DistributedRouter
from src.config import config
from src.crews.internal.research.agents  import create_project_manager_agent , create_internal_researcher_agent, create_online_researcher_agent
from src.crews.internal.research.tasks import internal_research_task, internal_analysis_task, project_management_task

def get_research_crew(router, tools, project_config, use_new_memory=False):
    """
    Wrapper function to create the research crew.

    Args:
        router: The DistributedRouter instance for model selection
        tools: List of tools to use
        project_config: Project configuration
        use_new_memory: Whether to use new memory instances for agents

    Returns:
        A Crew instance for research tasks
    """
    # Prepare inputs based on the project_config
    inputs = {
        "working_dir": project_config.get("crewai_settings", {}).get("working_directory", "/tmp"),
        "project_name": project_config.get("project", {}).get("name", "unknown"),
        "tools": tools,
    }
    # Call the correct crew creation function
    return create_research_crew(router, inputs, full_output=True)


def create_research_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    researcher_agent = create_internal_researcher_agent(router, inputs)
    analyst_agent = create_online_researcher_agent(router, inputs)
    # Fixed: Call the function instead of just referencing it
    project_manager_agent = create_project_manager_agent(router, inputs)

    tasks = [
        internal_research_task(researcher_agent, inputs),
        internal_analysis_task(analyst_agent, inputs),
        project_management_task(project_manager_agent, inputs),
    ]

    return Crew(
        agents=[researcher_agent, analyst_agent, project_manager_agent],
        tasks=tasks,
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )
