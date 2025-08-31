import importlib
import logging
import traceback
from pathlib import Path
from typing import Any, Dict, List, Optional
from rich.console import Console

from crewai import Crew, Process, Task
# Correct import for ErrorLogger from the same directory's agents file
from .agents import create_team_manager_agent, ErrorLogger, format_agent_list
# The DelegateWorkTool is used by the manager in the hierarchical process
from src.crews.internal.tools.delegate_tool import DelegateWorkTool

console = Console()
# The error_logger instance will be passed from the run script
# error_logger = ErrorLogger() # This line is removed as the instance is passed

def get_team_manager_crew(
    router,
    tools: List,
    project_config: Dict[str, Any],
    task_inputs: Dict[str, Any],
    crews_status: Dict[str, Any]
) -> Optional[Crew]:
    try:
        project_id = task_inputs.get("project_id", "default")
        prompt = task_inputs.get("prompt", "")
        # FIX: Retrieve error_logger from task_inputs
        error_logger = task_inputs.get("error_logger", ErrorLogger()) # Use a default if not passed
        working_dir_str = project_config.get("crewai_settings", {}).get(
            "working_directory", f"/tmp/internal_crew/{project_id}/")
        working_dir = Path(working_dir_str)
        working_dir.mkdir(parents=True, exist_ok=True)

        # Create the team manager agent
        team_manager = create_team_manager_agent(router=router, project_id=project_id, working_dir=working_dir)

        worker_agents = []
        for crew_name, info in crews_status.items():
            if crew_name == "team_manager":
                continue

            console.print(f"DEBUG: Checking crew '{crew_name}'. Status: {info.get('status')}", style="dim")
            if info.get("status") == "available" and "agents" in info:
                for func_name in info["agents"]:
                    if func_name == "create_team_manager_agent":
                        continue

                    console.print(f"DEBUG: Attempting to instantiate agent via: '{func_name}' from crew '{crew_name}'", style="dim")
                    try:
                        module_name = f"src.crews.internal.{crew_name}.agents"
                        agents_module = importlib.import_module(module_name)
                        agent_creator_func = getattr(agents_module, func_name)

                        # Call the agent creation function
                        # FIX: Pass error_logger instance to agent creators
                        agent = agent_creator_func(router=router, inputs=task_inputs, tools=tools, error_logger=error_logger)

                        worker_agents.append(agent)
                        console.print(f"DEBUG: Successfully instantiated agent via: '{func_name}'", style="green")
                    except Exception as e:
                        console.print(f"⚠️ Failed to import or instantiate agent creator '{func_name}", style="yellow")
                        console.print(f"Error: {e}", style="yellow")
                        console.print("Traceback:", style="dim")
                        console.print(traceback.format_exc(), style="dim")
