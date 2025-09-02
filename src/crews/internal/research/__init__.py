# src/crews/internal/research/__init__.py

"""
Research crew module for internal project research and analysis.
"""

from .agents import (
    create_project_manager_agent,
    create_internal_researcher_agent, 
    create_online_researcher_agent
)

from .tasks import (
    internal_research_task,
    internal_analysis_task
)

from .crew import (
    create_research_crew,
    get_research_crew
)

__all__ = [
    'create_project_manager_agent',
    'create_internal_researcher_agent',
    'create_online_researcher_agent',
    'internal_research_task', 
    'internal_analysis_task',
    'create_research_crew',
    'get_research_crew'
]