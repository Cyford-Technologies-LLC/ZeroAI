import importlib
import logging
import traceback
from pathlib import Path
from typing import Any, Dict, List, Optional
from rich.console import Console

from crewai import Crew, Process, Task
from .agents import create_team_manager_agent, ErrorLogger, format_agent_list
from src.crews.internal.tools.delegate_tool import DelegateWorkTool

console = Console()

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
        # Get error_logger from task_inputs
        error_logger = task_inputs.get("error_logger", ErrorLogger())
        working_dir_str = project_config.get("crewai_settings", {}).get(
            "working_directory", f"/tmp/internal_crew/{project_id}/")
        working_dir = Path(working_dir_str)
        working_dir.mkdir(parents=True, exist_ok=True)

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

                        agent = agent_creator_func(router=router, inputs=task_inputs, tools=tools, error_logger=error_logger)
                        worker_agents.append(agent)
                        console.print(f"DEBUG: Successfully instantiated agent via: '{func_name}'", style="green")
                    except Exception as e:
                        console.print(f"⚠️ Failed to import or instantiate agent creator '{func_name}' from '{crew_name}': {e}", style="yellow")
                        console.print(f"Full Traceback: {traceback.format_exc()}", style="yellow")

        if not worker_agents:
            console.print("❌ No worker agents found to form the crew. Delegation will fail.", style="red")
            return None

        console.print(f"👨‍💼 Assembling hierarchical crew with {len(worker_agents)} worker agents.", style="blue")

        initial_task = Task(
            description=f"Analyze and coordinate the following request: {prompt}",
            agent=team_manager,
            expected_output="A comprehensive plan outlining the steps and which specialized crew should execute them."
        )

        return Crew(
            agents=worker_agents,
            manager_agent=team_manager,
            tasks=[initial_task],
            process=Process.hierarchical,
            verbose=True
        )

    except Exception as e:
        error_context = {"traceback": traceback.format_exc()}
        error_logger.log_error(f"Error creating team manager crew: {str(e)}", error_context)
        console.print(f"❌ Error creating team manager crew: {e}", style="red")
        return None
