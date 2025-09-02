# src/crews/internal/team_manager/__init__.py

from .agents import ErrorLogger, AVAILABLE_AGENTS, format_agent_list, create_team_manager_agent, load_all_coworkers
from .crew import create_team_manager_crew
from .tasks import create_docker_task, create_project_task, create_agent_listing_task

__all__ = [
    "create_team_manager_crew", 
    "create_team_manager_agent",
    "load_all_coworkers",
    "ErrorLogger", 
    "AVAILABLE_AGENTS", 
    "format_agent_list",
    "create_docker_task",
    "create_project_task", 
    "create_agent_listing_task"
]
