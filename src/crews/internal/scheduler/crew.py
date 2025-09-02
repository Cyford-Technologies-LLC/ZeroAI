# src/crews/internal/scheduler/crew.py
from crewai import Crew, Process
from typing import Dict, Any, List
from distributed_router import DistributedRouter
from src.config import config
from .agents import create_scheduler_agent
from .tasks import schedule_management_task

def get_scheduler_crew(router, tools, project_config, use_new_memory=False):
    """
    Wrapper function to create the scheduler crew.
    """
    inputs = {
        "working_dir": project_config.get("crewai_settings", {}).get("working_directory", "/tmp"),
        "project_name": project_config.get("project", {}).get("name", "unknown"),
        "tools": tools,
    }
    return create_scheduler_crew(router, inputs, full_output=True)

def create_scheduler_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    """Creates a scheduler crew."""
    scheduler_agent = create_scheduler_agent(router, inputs)
    scheduler_task = schedule_management_task(scheduler_agent, inputs)

    return Crew(
        agents=[scheduler_agent],
        tasks=[scheduler_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )