# src/crews/internal/team_manager/crew.py
from crewai import Crew, Process, Task
from typing import Dict, Any, List, Optional
from src.distributed_router import DistributedRouter
from src.config import config
from .agents import create_team_manager_agent, load_all_coworkers
from src.utils.custom_logger_callback import CustomLogger
from pathlib import Path
from rich.console import Console
from src.utils.knowledge_utils import get_common_knowledge  # No longer need to import the instance
from crewai.knowledge.knowledge import Knowledge

console = Console()


def create_team_manager_crew(router: DistributedRouter, inputs: Dict[str, Any], tools: List,
                             project_config: Dict[str, Any], full_output: bool = False,
                             custom_logger: Optional[CustomLogger] = None) -> Crew:
    """Creates a Team Manager crew using the distributed router."""

    # First, load all coworkers
    all_coworkers = load_all_coworkers(router=router, inputs=inputs, tools=tools)

    # Move assignment to the top so it's defined
    crew_agents = all_coworkers

    # Create the manager agent with delegation tools
    manager_agent = create_team_manager_agent(
        router=router,
        project_id=inputs.get("project_id"),
        working_dir=inputs.get("working_dir", Path("/tmp")),
        inputs=inputs,
        coworkers=all_coworkers
    )

    # ... (tasks definition) ...
    sequential_tasks = []

    # Enable verbose on all agents
    for agent in crew_agents:
        agent.verbose = True

    # Find key agents and create tasks (your existing logic)
    project_manager = next((agent for agent in crew_agents if agent.role == "Project Manager"), None)
    code_researcher = next((agent for agent in crew_agents if "Code Researcher" in agent.role), None)
    senior_dev = next((agent for agent in crew_agents if "Senior Developer" in agent.role), None)

    if project_manager:
        sequential_tasks.append(Task(
            description=f"Analyze and plan the task: {inputs.get('prompt')}",
            agent=project_manager,
            expected_output="A detailed project plan and task breakdown.",
            callback=custom_logger.log_step_callback if custom_logger else None
        ))

    if code_researcher:
        sequential_tasks.append(Task(
            description=f"Research and analyze code requirements for: {inputs.get('prompt')}",
            agent=code_researcher,
            expected_output="Technical analysis and code recommendations.",
            callback=custom_logger.log_step_callback if custom_logger else None
        ))

    if senior_dev:
        sequential_tasks.append(Task(
            description=f"Implement solution for: {inputs.get('prompt')}",
            agent=senior_dev,
            expected_output="Complete implementation with code and documentation.",
            callback=custom_logger.log_step_callback if custom_logger else None
        ))

    # Fallback logic for tasks (your existing logic)
    if not sequential_tasks and crew_agents:
        sequential_tasks = [Task(
            description=inputs.get("prompt"),
            agent=crew_agents,
            expected_output="Complete solution to the user's request.",
            callback=custom_logger.log_step_callback if custom_logger else None
        )]

    project_id = inputs.get("project_id")
    repository = inputs.get("repository")

    if not project_id:
        raise ValueError("The 'project_id' key is missing from the inputs.")

    common_knowledge = get_common_knowledge(
        project_location=project_id,
        repository=repository
    )

    # Define the embedder as a dictionary for both Crew and Knowledge
    crew_embedder_config = {
        "provider": "ollama",
        "config": {
            "model": "nomic-embed-text",
            "base_url": "http://149.36.1.65:11434"
        }
    }

    # Attach knowledge to agents using the embedder dictionary
    for agent in all_coworkers:
        agent.knowledge = Knowledge(
            sources=common_knowledge,
            embedder=crew_embedder_config,  # <-- Pass the dictionary here
            collection_name=f"crew_knowledge_{project_id}"
        )

    crew1 = Crew(
        agents=crew_agents,
        tasks=sequential_tasks,
        process=Process.sequential,
        verbose=True,
        full_output=full_output,
        embedder=crew_embedder_config,  # <-- Pass the dictionary here
    )

    return crew1
