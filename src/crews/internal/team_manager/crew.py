# src/crews/internal/team_manager/crew.py
import importlib
import logging
import traceback
import inspect
from pathlib import Path
from typing import Any, Dict, List, Optional
from rich.console import Console

from crewai import Crew, Process, Task, Agent
from .agents import create_team_manager_agent, ErrorLogger
from src.crews.internal.tools.delegate_tool import DelegateWorkTool

console = Console()


# src/crews/internal/team_manager/crew.py

from crewai import Crew, Process
from typing import Dict, Any, List, Optional
from distributed_router import DistributedRouter
from config import config
from .agents import create_team_manager_agent, load_all_coworkers
from .tasks import get_team_manager_tasks
from src.utils.custom_logger_callback import CustomLogger


def get_team_manager_crew(router: DistributedRouter, inputs: Dict[str, Any], tools: List[Any],
                          project_config: Dict[str, Any], use_new_memory: bool = False) -> Crew:
    """
    Wrapper function to create the Team Manager crew.
    """
    # Create and return the Team Manager crew
    return create_team_manager_crew(router, inputs, tools=tools, project_config=project_config)


def create_team_manager_crew(router: DistributedRouter, inputs: Dict[str, Any], tools: List,
                             project_config: Dict[str, Any], full_output: bool = False) -> Crew:
    """Creates a Team Manager crew using the distributed router."""
    # This is the key fix: First, load all coworkers
    all_coworkers = load_all_coworkers(router=router, inputs=inputs, tools=tools)

    # Then, create the manager agent, passing the now-populated coworker list
    manager_agent = create_team_manager_agent(
        router=router,
        project_id=inputs.get("project_id"),
        working_dir=inputs.get("working_dir"),
        coworkers=all_coworkers
    )

    # Get the tasks for the manager
    manager_tasks = get_team_manager_tasks(manager_agent, inputs)

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
        # The custom logger is not needed in this simplified example
        # but would be added here if necessary
        # callbacks=[CustomLogger(log_file=f"{inputs.get('working_dir')}/crew_log.json")]
    )

