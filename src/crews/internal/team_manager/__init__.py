# src/crews/internal/team_manager/__init__.py

from .agent import ErrorLogger, AVAILABLE_AGENTS, format_agent_list
from .crew import get_team_manager_crew

# You might want to define which functions or classes to expose
# from the package using __all__ for cleaner imports.
__all__ = ["get_team_manager_crew", "ErrorLogger", "AVAILABLE_AGENTS", "format_agent_list"]
