# src/crews/internal/team_manager/crew.py
import importlib
import logging
import traceback
import inspect
from pathlib import Path
from typing import Any, Dict, List, Optional
from rich.console import Console

from crewai import Crew, Process, Task, Agent
from .agents import create_team_manager_agent, ErrorLogger
from src.crews.internal.tools.delegate_tool import DelegateWorkTool

console = Console()


def get_team_manager_crew(
        router,
        tools: List,
        project_config: Dict[str, Any],
        task_inputs: Dict[str, Any],
        crews_status: Dict[str, Any],
        custom_logger: Any,
        step_callback: Optional[Any] = None
) -> Optional[Crew]:
    """
    Creates and returns a hierarchical Crew for a team manager, dynamically
    including available worker agents from other crews.
    """
    try:
        project_id = task_inputs.get("project_id", "default")
        prompt = task_inputs.get("prompt", "")
        error_logger = task_inputs.get("error_logger", ErrorLogger())
        working_dir_str = project_config.get("crewai_settings", {}).get(
            "working_directory", f"/tmp/internal_crew/{project_id}/"
        )
        working_dir = Path(working_dir_str)
        working_dir.mkdir(parents=True, exist_ok=True)

        worker_agents = []
        for crew_name, info in crews_status.items():
            if crew_name == "team_manager":
                continue

            console.print(f"DEBUG: Checking crew '{crew_name}'. Status: {info.get('status')}", style="dim")
            if info.get("status") == "available" and "agents" in info:
                for func_name in info["agents"]:
                    if func_name == "create_team_manager_agent":
                        continue

                    console.print(f"DEBUG: Attempting to instantiate agent via: '{func_name}' from crew '{crew_name}'",
                                  style="dim")
                    try:
                        module_name = f"src.crews.internal.{crew_name}.agents"
                        agents_module = importlib.import_module(module_name)
                        agent_creator_func = getattr(agents_module, func_name)

                        func_params = inspect.signature(agent_creator_func).parameters
                        call_kwargs = {'router': router, 'inputs': task_inputs, 'tools': tools}
                        if 'coworkers' in func_params:
                            call_kwargs['coworkers'] = []

                        agent = agent_creator_func(**call_kwargs)
                        if isinstance(agent, Agent):
                            worker_agents.append(agent)
                            console.print(f"DEBUG: Successfully instantiated agent via: '{func_name}'", style="green")
                        else:
                            console.print(f"⚠️ Agent creation function '{func_name}' returned a non-Agent object.",
                                          style="yellow")
                    except Exception as e:
                        console.print(
                            f"⚠️ Failed to import or instantiate agent creator '{func_name}' from '{crew_name}': {e}",
                            style="yellow")
                        console.print(f"Full Traceback: {traceback.format_exc()}", style="yellow")
                        error_logger.log_error(f"Failed to instantiate agent creator {func_name}",
                                               {"exception": str(e), "traceback": traceback.format_exc()})

        worker_agents = [agent for agent in worker_agents if isinstance(agent, Agent)]
        console.print(f"DEBUG: Found {len(worker_agents)} worker agents.", style="blue")

        if not worker_agents:
            console.print("❌ No worker agents found to form the crew. Delegation will fail.", style="red")
            return None

        team_manager = create_team_manager_agent(
            router=router,
            project_id=project_id,
            working_dir=working_dir,
            coworkers=worker_agents,
        )

        initial_task = Task(
            description=f"Analyze and coordinate the following request: {prompt}. Your specialized crew members include: {', '.join([a.role for a in worker_agents])}. Inform all team members about the request and delegate tasks accordingly.",
            agent=team_manager,
            expected_output="A comprehensive plan outlining the steps and which specialized crew should execute them, including confirmation that the full crew roster has been shared."
        )

        console.print(f"👨‍💼 Assembling hierarchical crew with {len(worker_agents)} worker agents.", style="blue")

        # REVISED: Pass step_callback to the Crew constructor
        hierarchical_crew = Crew(
            agents=worker_agents,
            manager_agent=team_manager,
            tasks=[initial_task],
            process=Process.hierarchical,
            # REVISED: Ensure verbose is a boolean
            verbose=bool(task_inputs.get("verbose", 1)),
            # REVISED: Use step_callback instead of the deprecated callbacks
            step_callback=step_callback
        )

        manager_tool_names = [tool.name for tool in hierarchical_crew.manager_agent.tools]
        console.print(f"DEBUG: Manager agent tools: {manager_tool_names}", style="dim")
        for tool in hierarchical_crew.manager_agent.tools:
            if "Delegate work" in tool.name:
                console.print(f"DEBUG: Delegate tool description: {tool.description}", style="dim")
            if "Ask question" in tool.name:
                console.print(f"DEBUG: Ask tool description: {tool.description}", style="dim")

        return hierarchical_crew

    except Exception as e:
        error_context = {"traceback": traceback.format_exc()}
        error_logger = task_inputs.get("error_logger", ErrorLogger())
        error_logger.log_error(f"Error creating team manager crew: {str(e)}", error_context)
        console.print(f"❌ Error creating team manager crew: {e}", style="red")
        return None
