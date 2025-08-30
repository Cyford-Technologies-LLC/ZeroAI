# src/crews/internal/team_manager/crew.py

import logging
import traceback
from typing import Dict, Any, List, Optional
from crewai import Crew, Process, Task
from pathlib import Path
from rich.console import Console

from .agent import create_team_manager_agent, ErrorLogger, format_agent_list

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
    Create a crew with just the Team Manager agent.

    Args:
        router: The LLM router instance
        tools: List of tools (not used by Team Manager)
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

        # Create the team manager agent (without tools)
        team_manager = create_team_manager_agent(
            router=router,
            project_id=project_id,
            working_dir=working_dir
        )

        # Create introduction task
        intro_task = Task(
            description=f"""
            You are the Team Manager for project {project_id}.

            First, introduce yourself and your capabilities. Then, list all available specialist teams
            that you can delegate tasks to.

            {format_agent_list()}

            Finally, analyze the following task request and determine which specialist teams
            would be best suited to handle it:

            TASK: {prompt}
            CATEGORY: {category}

            Provide a plan for how you would delegate this task to the appropriate specialists.
            """,
            agent=team_manager,
            expected_output="Introduction, available teams listing, and task delegation plan."
        )

        # Create the main task execution task
        execution_task = Task(
            description=f"""
            TASK: {prompt}

            PROJECT: {project_id}
            CATEGORY: {category}
            WORKING DIRECTORY: {working_dir}

            As the Team Manager, delegate and coordinate this task:

            1. Break down the task into appropriate subtasks
            2. Assign each subtask to the appropriate specialist team
            3. Coordinate the execution of subtasks
            4. Integrate the results into a cohesive solution
            5. Provide a summary of what was accomplished

            {format_agent_list()}
            """,
            agent=team_manager,
            expected_output="Complete execution of the requested task through delegation."
        )

        # Create the crew with the team manager and tasks
        crew = Crew(
            agents=[team_manager],
            tasks=[intro_task, execution_task],
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