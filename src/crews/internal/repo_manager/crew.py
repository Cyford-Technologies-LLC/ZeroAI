# src/crews/internal/repo_manager/crew.py
from crewai import Crew, Process
from typing import Dict, Any, List
from distributed_router import DistributedRouter
from src.config import config
from .agents import create_git_operator_agent
from .tasks import clone_repo_task, commit_and_push_task


def create_repo_manager_crew(router: DistributedRouter, inputs: Dict[str, Any], tools: List,
                             full_output: bool = False) -> Crew:
    """
    Creates a repo manager crew using the distributed router.

    Args:
        router: The DistributedRouter instance for model selection
        inputs: A dictionary of task-specific inputs.
        tools: A list of tools to be used by the agents.
        full_output: Whether to return the full execution output.

    Returns:
        A Crew instance for repository management tasks
    """
    git_operator_agent = create_git_operator_agent(router, inputs, tools)

    tasks = [
        clone_repo_task(git_operator_agent, inputs),
        commit_and_push_task(git_operator_agent, inputs)
    ]

    return Crew(
        agents=[git_operator_agent],
        tasks=tasks,
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output,
        memory=True
    )
