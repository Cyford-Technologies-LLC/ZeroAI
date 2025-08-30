# src/crews/internal/repo_manager/crew.py

from crewai import Crew, Process
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from .agents import create_git_operator_agent
from .tasks import clone_repo_task, commit_and_push_task


def get_repo_manager_crew(router, tools, project_config, use_new_memory=False):
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


def create_repo_manager_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    git_operator_agent = create_git_operator_agent(router, inputs)

    tasks = [
        clone_repo_task(git_operator_agent, inputs),
        commit_and_push_task(git_operator_agent, inputs)
    ]

    return Crew(
        agents=[git_operator_agent],
        tasks=tasks,
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )
