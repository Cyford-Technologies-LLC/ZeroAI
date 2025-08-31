# src/crews/internal/team_manager/crew.py

import importlib
import logging
import traceback
from pathlib import Path
from typing import Any, Dict, List, Optional
from rich.console import Console
from crewai import Crew, Process, Task, Agent
from .agents import create_team_manager_agent, format_agent_list
# Import ErrorLogger
from src.utils.error_logging import ErrorLogger

console = Console()
error_logger = ErrorLogger()

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
        working_dir_str = project_config.get("crewai_settings", {}).get(
            "working_directory", f"/tmp/internal_crew/{project_id}/")
        working_dir = Path(working_dir_str)
        working_dir.mkdir(parents=True, exist_ok=True)

        # Create the team manager agent, but redefine its purpose as a router
        team_manager = create_team_manager_agent(router=router, project_id=project_id, working_dir=working_dir)
        team_manager.role = "Task Router"
        team_manager.goal = (
            "Analyze an incoming request and output the role of the most suitable specialist agent to handle it. "
            "Your output must be a single word corresponding to the worker's role."
        )
        team_manager.backstory = "You are a highly efficient dispatcher who routes tasks to the correct expert."
        team_manager.tools = []
        team_manager.allow_delegation = False

        worker_agents = {}
        for crew_name, info in crews_status.items():
            if crew_name == "team_manager":
                continue

            console.print(f"DEBUG: Checking crew '{crew_name}'. Status: {info.get('status')}", style="dim")
            if info.get("status") == "available" and "agents" in info:
                for func_name in info["agents"]:
                    if func_name == "create_team_manager_agent":
                        continue

                    try:
                        module_name = f"src.crews.internal.{crew_name}.agents"
                        agents_module = importlib.import_module(module_name)
                        agent_creator_func = getattr(agents_module, func_name)
                        agent = agent_creator_func(router=router, inputs=task_inputs, tools=tools)
                        worker_agents[agent.role] = agent
                    except Exception as e:
                        console.print(f"⚠️ Failed to import agent creator '{func_name}': {e}", style="yellow")
                        console.print(f"Full Traceback: {traceback.format_exc()}", style="yellow")

        if not worker_agents:
            console.print("❌ No worker agents found to form the crew. Aborting.", style="red")
            return None

        console.print(f"👨‍💼 Assembling sequential crew with a routing manager and {len(worker_agents)} worker agents.", style="blue")

        # Define the routing task for the manager
        initial_task = Task(
            description=(
                f"Analyze the following request: '{prompt}'. "
                f"Your final answer MUST be only the role of the single best-suited agent from this list: {list(worker_agents.keys())}."
            ),
            agent=team_manager,
            expected_output="The role of the worker agent (e.g., 'Developer')."
        )

        # Assemble a sequential crew for the routing task only
        return Crew(
            agents=[team_manager],  # Only the router agent is needed for this first step
            tasks=[initial_task],
            process=Process.sequential,
            verbose=True
        )

    except Exception as e:
        error_context = {"traceback": traceback.format_exc()}
        error_logger.log_error(f"Error creating team manager crew: {str(e)}", error_context)
        console.print(f"❌ Error creating team manager crew: {e}", style="red")
        return None
