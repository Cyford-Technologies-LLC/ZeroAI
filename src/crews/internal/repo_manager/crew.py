from crewai import Crew, Process
from typing import Dict, Any, List
from distributed_router import DistributedRouter
from config import config
from .agents import create_git_operator_agent
from .tasks import clone_repo_task, commit_and_push_task


def get_repo_manager_crew(router: DistributedRouter, tools: List, project_config: Dict[str, Any],
                          inputs: Dict[str, Any], use_new_memory: bool = False) -> Crew:
    """
    Wrapper function to create the repo manager crew.

    Args:
        router: The DistributedRouter instance for model selection
        tools: List of tools to use
        project_config: Project configuration
        inputs: A dictionary of task-specific inputs.
        use_new_memory: Whether to use new memory instances for agents

    Returns:
        A Crew instance for repository management tasks
    """
    # Create and return the repo manager crew, passing all necessary arguments
    return create_repo_manager_crew(router, inputs, tools=tools, project_config=project_config)


def create_repo_manager_crew(router: DistributedRouter, inputs: Dict[str, Any], tools: List,
                             project_config: Dict[str, Any], full_output: bool = False) -> Crew:
    # Get any coworkers from inputs if needed, though they aren't used here directly
    # They are part of the team manager's delegation, not this sequential crew
    # In a sequential crew, coworkers are not necessary for the agent definition

    # Create the agent, passing the required router, inputs, and tools
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
        full_output=full_output
    )
