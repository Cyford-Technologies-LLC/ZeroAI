# src/crews/internal/team_manager/crew.py

from crewai import Crew, Process, Task
from typing import Dict, Any, List, Optional
from distributed_router import DistributedRouter
from config import config
from .agents import create_team_manager_agent, load_all_coworkers
from src.utils.custom_logger_callback import CustomLogger
from pathlib import Path


def create_team_manager_crew(router: DistributedRouter, inputs: Dict[str, Any], tools: List,
                             project_config: Dict[str, Any], full_output: bool = False) -> Crew:
    """Creates a Team Manager crew using the distributed router."""
    # This is the key fix: First, load all coworkers
    all_coworkers = load_all_coworkers(router=router, inputs=inputs, tools=tools)

    # Then, create the manager agent, passing the now-populated coworker list
    manager_agent = create_team_manager_agent(
        router=router,
        project_id=inputs.get("project_id"),
        working_dir=inputs.get("working_dir", Path("/tmp")),
        coworkers=all_coworkers
    )

    # Define tasks directly within this function
    manager_tasks = [
        Task(
            description=inputs.get("prompt"),
            agent=manager_agent,
            # FIX: Add the required 'expected_output' field
            expected_output="A final, complete, and thoroughly reviewed solution to the user's request. "
                            "This may include code, documentation, or other relevant artifacts."
        )
    ]

    # The agents parameter should be a flat list of all agent objects
    # including the manager and all workers.
    crew_agents = [manager_agent] + all_coworkers

    # Create and return the crew with the correct agent list
    return Crew(
        agents=crew_agents,
        tasks=manager_tasks,
        manager_agent=manager_agent,
        process=Process.hierarchical,
        verbose=config.agents.verbose,
        full_output=full_output,
    )

