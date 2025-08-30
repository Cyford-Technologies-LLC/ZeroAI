# src/crews/internal/team_manager/crew.py

import logging
import traceback
from typing import Dict, Any, List, Optional
from crewai import Crew, Process
from pathlib import Path
from rich.console import Console

from .agent import create_team_manager_agent, ErrorLogger
from .tasks import create_docker_task, create_project_task, create_agent_listing_task

# Configure console for rich output
console = Console()
error_logger = ErrorLogger()

def get_team_manager_crew(
    router,
    tools: List,
    project_config: Dict[str, Any],
    task_inputs: Dict[str, Any]
) -> Crew:
    """
    Create a crew with the Team Manager as the primary agent.

    Args:
        router: The LLM router instance
        tools: List of tools available to the agent
        project_config: The project configuration
        task_inputs: Dictionary of task inputs

    Returns:
        A Crew instance with the Team Manager
    """
    try:
        # Extract task information
        project_id = task_inputs.get("project_id", "default")
        prompt = task_inputs.get("prompt", "")
        category = task_inputs.get("category", "general")

        # Set up working directory
        working_dir_str = project_config.get("crewai_settings", {}).get(
            "working_directory", f"/tmp/internal_crew/{project_id}/")
        task_id = task_inputs.get("task_id", "")
        if task_id:
            working_dir_str = working_dir_str.replace("{task_id}", task_id)
        working_dir = Path(working_dir_str)
        working_dir.mkdir(parents=True, exist_ok=True)

        # Create the team manager agent
        team_manager = create_team_manager_agent(
            router=router,
            project_id=project_id,
            working_dir=working_dir,
            tools=tools
        )

        # Determine the appropriate task based on the prompt
        tasks = []

        # Always start with agent introduction task
        intro_task = create_agent_listing_task(team_manager)
        tasks.append(intro_task)

        # Check if this is a Docker-related task
        if any(keyword in prompt.lower() for keyword in
               ["docker", "container", "containerize", "containerization", "dockerfile"]):
            main_task = create_docker_task(
                agent=team_manager,
                project_id=project_id,
                prompt=prompt,
                working_dir=working_dir
            )
        else:
            # Create a task based on the category
            main_task = create_project_task(
                agent=team_manager,
                project_id=project_id,
                prompt=prompt,
                category=category,
                working_dir=working_dir
            )

        tasks.append(main_task)

        # Create the crew
        crew = Crew(
            agents=[team_manager],
            tasks=tasks,
            process=Process.sequential,
            verbose=True
        )

        return crew

    except Exception as e:
        error_context = {
            "project_id": task_inputs.get("project_id", "unknown"),
            "prompt": task_inputs.get("prompt", "unknown"),
            "category": task_inputs.get("category", "unknown"),
            "traceback": traceback.format_exc()
        }

        error_logger.log_error(f"Error creating team manager crew: {str(e)}", error_context)
        console.print(f"❌ Error creating team manager crew: {e}", style="red")
        raise