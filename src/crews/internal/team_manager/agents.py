# src/crews/internal/team_manager/agents.py
import importlib
import inspect
import sys
import traceback
import uuid
from datetime import datetime
from pathlib import Path
from typing import Dict, Any, List, Optional

# The InternalPeerCheckTool import should still be here,
# but it should be used for the worker agents, not the manager.
from src.crews.internal.team_manager.tools import InternalPeerCheckTool
from crewai import Agent
from rich.console import Console

# ... (other imports and helper functions like ErrorLogger, format_agent_list, discover_available_crews, load_all_coworkers) ...

def create_team_manager_agent(router, project_id: str, working_dir: Path, coworkers: Optional[List] = None) -> Agent:
    """
    Creates and configures the Team Manager Agent.

    Args:
        router: The DevOps router instance for LLM routing.
        project_id: The ID of the project being worked on.
        working_dir: The project's working directory.
        coworkers: A list of all other agents in the crew for delegation.

    Returns:
        The configured CrewAI Team Manager Agent.
    """
    try:
        llm = router.get_llm_for_role("team_manager")
        available_crews = discover_available_crews()
        crew_list = format_agent_list()

        console.print(f"👨‍💼 Creating Team Manager agent...", style="blue")

        # The manager agent should NOT have tools assigned in a hierarchical crew.
        # It uses its `allow_delegation` capability to hand off work to other agents.
        # Any 'InternalPeerCheckTool' should be assigned to the worker agents.
        # peer_check_tool = InternalPeerCheckTool(coworkers=coworkers) # This line is removed to fix the error.

        team_manager = Agent(
            role="Team Manager",
            name="Project Coordinator",
            goal=f"Coordinate specialists to complete tasks for project {project_id} by delegating work efficiently, avoiding redundant questions, and making logical decisions based on coworker feedback.",
            backstory=f"""You are the expert Project Coordinator for project {project_id}. Your role is to analyze tasks, 
            delegate work to appropriate specialists, and oversee the project's progress. You do not perform technical tasks yourself; 
            you rely on the specialist teams available to you. Your primary directive is to use your delegation capability to assign tasks to the most suitable specialist team.""",
            allow_delegation=True,
            verbose=True,
            llm=llm,
        )
        return team_manager

    except Exception as e:
        error_message = f"Error creating Team Manager agent: {e}"
        ErrorLogger().log_error(error_message, {"traceback": traceback.format_exc()})
        raise Exception(error_message)

