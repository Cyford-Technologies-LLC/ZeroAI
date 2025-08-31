# src/crews/internal/team_manager/agent.py

import importlib
import inspect
import logging
import sys
import traceback
import uuid
from datetime import datetime
from pathlib import Path
from typing import Dict, Any, List, Optional

from crewai import Agent
from rich.console import Console

# ... [rest of the file remains the same] ...

def create_team_manager_agent(router, project_id: str, working_dir: Path) -> Agent:
    """
    Create the Team Manager Agent that only delegates tasks (no direct tools).

    Args:
        router: The LLM router instance
        project_id: The project identifier
        working_dir: The working directory for file operations

    Returns:
        An Agent instance configured as Team Manager
    """
    try:
        # Get LLM for the team manager role
        llm = router.get_llm_for_role("devops_orchestrator")

        # Discover available crews
        available_crews = discover_available_crews()
        crew_list = format_agent_list()

        console.print(f"👨‍💼 Creating Team Manager agent...", style="blue")

        # Create the team manager agent
        team_manager = Agent(
            role="Team Manager",
            name="Project Coordinator",
            goal=f"Coordinate specialists to complete tasks for project {project_id}",
            backstory=f"""You are the expert Project Coordinator for project {project_id}. Your role is to analyze tasks, 
            delegate work to appropriate specialists, and oversee the project's progress. You do not perform technical work directly.

            You must follow these instructions precisely:
            1. **Analyze Requirements**: Thoroughly understand the request.
            2. **Identify Specialists**: Determine which available specialists are best suited for each sub-task.
            3. **Delegate Effectively**: Use the 'Delegate work to coworker' tool to delegate tasks. Your delegation must provide all necessary context to the specialist, as they have no prior knowledge of the task.
            4. **Formulate Correct Inputs**: When using a tool, ensure the `Action Input` you generate strictly adheres to the schema provided for that tool.
            5. **Learn from Failures**: If a tool execution results in an error, you must analyze the error message. Do not repeat the same invalid action. Instead, formulate a new, corrected approach based on the error received.
            6. **Ask for Clarification**: If you are unsure how to proceed, use the 'Ask question to coworker' tool to get more information from a specialist.

            Available Specialists and their Capabilities:
            {crew_list}
            
            Your working directory is: {working_dir}
            """,
            llm=llm,
            verbose=True,
            allow_delegation=True,
        )

        return team_manager

    except Exception as e:
        error_logger = ErrorLogger()
        error_logger.log_error(
            f"Error creating team manager agent: {str(e)}",
            {
                "project_id": project_id,
                "working_dir": str(working_dir),
                "traceback": traceback.format_exc(),
                "sys.path": sys.path
            }
        )
        return None
