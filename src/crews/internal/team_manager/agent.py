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

# Configure console for rich output
console = Console()

class ErrorLogger:
    """Logs errors to a file for later review."""

    def __init__(self):
        self.error_dir = Path("src/errors")
        self.error_dir.mkdir(parents=True, exist_ok=True)

    def log_error(self, error_message: str, context: Dict[str, Any] = None) -> str:
        """
        Log an error to a file for later review.

        Args:
            error_message: The error message to log
            context: Additional context information

        Returns:
            The path to the error log file
        """
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        error_id = str(uuid.uuid4())[:8]
        filename = f"error_{timestamp}_{error_id}.log"
        filepath = self.error_dir / filename

        with open(filepath, 'w') as f:
            f.write(f"ERROR: {error_message}\n")
            f.write(f"TIMESTAMP: {datetime.now().isoformat()}\n")
            f.write(f"ERROR_ID: {error_id}\n\n")

            if context:
                f.write("CONTEXT:\n")
                for key, value in context.items():
                    f.write(f"  {key}: {value}\n")

        console.print(f"📝 Error logged to {filepath}", style="yellow")
        return str(filepath)

def discover_available_crews() -> Dict[str, Dict[str, str]]:
    """
    Scan the project to discover available crews and their agents.
    This provides a dynamic list of what's actually accessible.

    Returns:
        A dictionary mapping crew names to details
    """
    available_crews = {}
    errors = []

    # Check the internal crews directory
    crews_path = Path("src/crews/internal")
    if not crews_path.exists() or not crews_path.is_dir():
        error_message = f"Internal crews directory not found at {crews_path}"
        errors.append(error_message)
        console.print(f"⚠️ {error_message}", style="yellow")
        return {"errors": errors}

    # Scan each subdirectory in the internal crews path
    for crew_dir in crews_path.iterdir():
        if crew_dir.is_dir() and crew_dir.name != "__pycache__" and crew_dir.name != "team_manager":
            crew_name = crew_dir.name
            crew_info = {"path": str(crew_dir)}

            # Try to import the crew's agents module
            try:
                module_name = f"src.crews.internal.{crew_name}.agents"
                agents_module = importlib.import_module(module_name)

                # Look for agent creation functions
                agent_creators = []
                for name, obj in inspect.getmembers(agents_module):
                    if inspect.isfunction(obj) and name.startswith("create_"):
                        agent_creators.append(name)

                if agent_creators:
                    crew_info["agents"] = agent_creators
                    crew_info["status"] = "available"
                else:
                    crew_info["status"] = "no_agents"
                    crew_info["error"] = "No agent creation functions found"

            except ImportError as e:
                crew_info["status"] = "import_failed"
                crew_info["error"] = str(e)
                error_message = f"Failed to import {module_name}: {e}"
                errors.append(error_message)
            except Exception as e:
                crew_info["status"] = "error"
                crew_info["error"] = str(e)
                error_message = f"Error examining {module_name}: {e}"
                errors.append(error_message)

            available_crews[crew_name] = crew_info

    # Add any errors to the results
    if errors:
        available_crews["errors"] = errors

    return available_crews

def format_crew_list(crews_info: Dict[str, Dict[str, str]]) -> str:
    """
    Format the list of available crews as a string.

    Args:
        crews_info: Dictionary of crew information

    Returns:
        A formatted string listing the crews
    """
    if not crews_info or (len(crews_info) == 1 and "errors" in crews_info):
        return "# No available crews were detected\n\nPlease check the implementation of the internal crews."

    crew_list = "# Available Crews\n\n"

    for crew_name, info in crews_info.items():
        if crew_name == "errors":
            continue

        crew_list += f"## {crew_name.replace('_', ' ').title()}\n"

        if info.get("status") == "available":
            crew_list += "**Status**: ✅ Available\n\n"
            crew_list += "**Agents**:\n"
            for agent in info.get("agents", []):
                # Format the agent name from create_xyz_agent to "XYZ Agent"
                agent_name = agent.replace("create_", "").replace("_", " ").title()
                crew_list += f"- {agent_name}\n"
        else:
            crew_list += f"**Status**: ❌ Not Available\n\n"
            crew_list += f"**Error**: {info.get('error', 'Unknown error')}\n"

        crew_list += "\n"

    # Add any errors
    if "errors" in crews_info:
        crew_list += "## Errors Encountered\n\n"
        for error in crews_info["errors"]:
            crew_list += f"- {error}\n"

    return crew_list

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
        llm = router.get_llm_for_role("devops_orchestrator")  # Reusing existing role for compatibility

        # Discover available crews
        available_crews = discover_available_crews()
        crew_list = format_crew_list(available_crews)

        console.print(f"👨‍💼 Creating Team Manager agent...", style="blue")
        console.print(f"Found {len(available_crews) - (1 if 'errors' in available_crews else 0)} crews", style="blue")

        # Create the team manager agent
        team_manager = Agent(
            role="Team Manager",
            name="Project Coordinator",
            goal=f"Coordinate specialists to complete tasks for project {project_id}",
            backstory=f"""I am the Team Manager for project {project_id}. My role is to analyze tasks and
            delegate work to appropriate specialists. I don't perform technical work directly.

            {crew_list}

            Working directory: {working_dir}
            """,
            llm=llm,
            verbose=True,
            allow_delegation=True  # Allow delegation to other agents
        )

        return team_manager

    except Exception as e:
        error_logger = ErrorLogger()
        error_logger.log_error(
            f"Error creating team manager agent: {str(e)}",
            {
                "project_id": project_id,
                "working_dir": str(working_dir),
                "traceback": traceback.format_exc()
            }
        )
        console.print(f"❌ Error creating team manager agent: {e}", style="red")
        raise