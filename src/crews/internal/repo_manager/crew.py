from crewai import Crew, Process
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from .agents import create_git_operator_agent
from .tasks import clone_repo_task, commit_and_push_task


def create_repo_management_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
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
