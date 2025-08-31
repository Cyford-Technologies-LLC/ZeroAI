# src/crews/internal/team_manager/crew.py

import importlib
import logging
import traceback
from pathlib import Path
from typing import Any, Dict, List, Optional
from rich.console import Console

from crewai import Crew, Process, Task
from .agents import create_team_manager_agent, ErrorLogger
from src.crews.internal.tools.delegate_tool import DelegateWorkTool

console = Console()

def get_team_manager_crew(
    router,
    tools: List,
    project_config: Dict[str, Any],
    task_inputs: Dict[str, Any],
    crews_status: Dict[str, Any]
) -> Optional[Crew]:
    """
    Creates and returns a hierarchical Crew for a team manager, dynamically
    including available worker agents from other crews.

    Args:
        router: The router to use for LLM selection.
        tools: A list of tools to be used by the agents.
        project_config: The project's configuration dictionary.
        task_inputs: A dictionary of task-specific inputs.
        crews_status: A dictionary containing the status of other available crews.

    Returns:
        A Crew object configured for a hierarchical process, or None if creation fails.
    """
    try:
        project_id = task_inputs.get("project_id", "default")
        prompt = task_inputs.get("prompt", "")
        # Retrieve error_logger from task_inputs
        error_logger = task_inputs.get("error_logger", ErrorLogger())
        working_dir_str = project_config.get("crewai_settings", {}).get(
            "working_directory", f"/tmp/internal_crew/{project_id}/")
        working_dir = Path(working_dir_str)
        working_dir.mkdir(parents=True, exist_ok=True)

        # 1. Create the team manager agent.
        team_manager = create_team_manager_agent(router=router, project_id=project_id, working_dir=working_dir)

        # 2. Dynamically instantiate worker agents from other available crews.
        worker_agents = []
        for crew_name, info in crews_status.items():
            if crew_name == "team_manager":
                continue

            console.print(f"DEBUG: Checking crew '{crew_name}'. Status: {info.get('status')}", style="dim")
            if info.get("status") == "available" and "agents" in info:
                for func_name in info["agents"]:
                    # Skip manager agents from being added as workers.
                    if func_name == "create_team_manager_agent":
                        continue

                    console.print(f"DEBUG: Attempting to instantiate agent via: '{func_name}' from crew '{crew_name}'", style="dim")
                    try:
                        module_name = f"src.crews.internal.{crew_name}.agents"
                        agents_module = importlib.import_module(module_name)
                        agent_creator_func = getattr(agents_module, func_name)
                        # Pass the shared toolset to the worker agents.
                        agent = agent_creator_func(router=router, inputs=task_inputs, tools=tools)
                        worker_agents.append(agent)
                        console.print(f"DEBUG: Successfully instantiated agent via: '{func_name}'", style="green")
                    except Exception as e:
                        console.print(f"⚠️ Failed to import or instantiate agent creator '{func_name}' from '{crew_name}': {e}", style="yellow")
                        console.print(f"Full Traceback: {traceback.format_exc()}", style="yellow")
                        error_logger.log_error(f"Failed to instantiate agent creator {func_name}", {"exception": str(e), "traceback": traceback.format_exc()})

        if not worker_agents:
            console.print("❌ No worker agents found to form the crew. Delegation will fail.", style="red")
            return None

        # 3. Define the initial task for the manager.
        initial_task = Task(
            description=f"Analyze and coordinate the following request: {prompt}",
            agent=team_manager,
            expected_output="A comprehensive plan outlining the steps and which specialized crew should execute them."
        )

        console.print(f"👨‍💼 Assembling hierarchical crew with {len(worker_agents)} worker agents.", style="blue")

        # 4. Return the Crew instance with the correct configuration.
        # FIX: Pass only the worker_agents to the 'agents' parameter.
        return Crew(
            agents=worker_agents,
            manager_agent=team_manager,
            tasks=[initial_task],
            process=Process.hierarchical,
            verbose=task_inputs.get("verbose", 1),
        )

    except Exception as e:
        error_context = {"traceback": traceback.format_exc()}
        error_logger = task_inputs.get("error_logger", ErrorLogger())
        error_logger.log_error(f"Error creating team manager crew: {str(e)}", error_context)
        console.print(f"❌ Error creating team manager crew: {e}", style="red")
        return None
