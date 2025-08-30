from crewai import Crew, Process
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config

from src.crews.internal.documentation.agents import create_writer_agent
from .tasks import update_docs_task

# src/crews/internal/documentation/crew.py

def get_documentation_crew(router, tools, project_config, use_new_memory=False):
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

    # Create and return the documentation crew
    return create_documentation_crew(router, inputs, full_output=True)

def create_documentation_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    writer_agent = create_writer_agent(router, inputs)
    writer_task = update_docs_task(writer_agent, inputs)

    return Crew(
        agents=[writer_agent],
        tasks=[writer_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )
