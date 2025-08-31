#!/usr/bin/env python3
"""
AI DevOps Crew Runner

This script safely executes the internal DevOps AI crew for secure development operations.
It provides a secure command-line interface to trigger internal maintenance tasks.
"""

import sys
import os
import argparse
import json
import time
import uuid
import logging
import traceback
from pathlib import Path
from rich.console import Console
import yaml
from src.crews.internal.diagnostics.agents import create_diagnostic_agent
from crewai import Agent, Crew, Task, Process
from src.ai_dev_ops_crew import run_ai_dev_ops_crew_securely
from io import StringIO
from ast import literal_eval
from src.devops_router import get_router
from src.crews.internal.team_manager.agents import create_team_manager_agent, load_all_coworkers
from src.utils.loop_detection import LoopDetector  # Import the new class

from src.peer_discovery import PeerDiscovery

logger = logging.getLogger(__name__)

crew_type = os.getenv("CREW_TYPE")

from src.learning.task_manager import TaskManager

logger = logging.getLogger(__name__)
task_manager = TaskManager()

# Add the project root to the Python path to make imports work
project_root = Path(__file__).parent.parent.parent
sys.path.insert(0, str(project_root))

# Configure console for rich output
console = Console()


# Helper function to ensure directory exists
def ensure_dir_exists(directory_path):
    """Ensure that a directory exists, creating it if necessary."""
    if isinstance(directory_path, str):
        directory_path = Path(directory_path)

    directory_path.mkdir(parents=True, exist_ok=True)
    return directory_path


# Setup argument parser
def setup_arg_parser():
    """Set up and return the argument parser."""
    parser = argparse.ArgumentParser(description="Run the AI DevOps Crew")

    # Required task prompt argument
    parser.add_argument("prompt", help="The task description or prompt")

    # Optional arguments
    parser.add_argument("--project", default="default",
                        help="Project identifier (e.g., 'zeroai' or 'cyford/zeroai')")
    parser.add_argument("--category", default="general",
                        help="Task category (developer, documentation, repo_manager, research)")
    parser.add_argument("--task-id", default=None,
                        help="Task id for tracking (auto-generated if not provided)")
    parser.add_argument("--repo", default=None,
                        help="Git repository URL")
    parser.add_argument("--branch", default=None,
                        help="Git branch name")
    parser.add_argument("--verbose", "-v", action="store_true",
                        help="Enable verbose output")
    parser.add_argument("--dry-run", action="store_true",
                        help="Only simulate execution without making changes")

    return parser


# Load project configuration
def load_project_config(project_path: str, project_root: Path) -> dict:
    """
    Load project configuration from YAML file, supporting nested directories.

    Args:
        project_path: The path to the project relative to internal_crew (e.g., 'zeroai' or 'cyford/zeroai').
        project_root: The root Path of the project.

    Returns:
        Dictionary with project configuration.
    """
    config_dir_root = project_root / "knowledge" / "internal_crew"
    config_path = config_dir_root / project_path / "project_config.yaml"
    config_dir = config_path.parent

    # Check if the config file exists
    if not config_path.exists():
        console.print(f"⚠️ No config found for project at '{config_path}', creating default", style="yellow")
        ensure_dir_exists(config_dir)

        default_config = {
            "project_name": project_path.split('/')[-1],
            "description": "Auto-generated project configuration",
            "repository": None,
            "default_branch": "main",
            "created_at": time.strftime("%Y-%m-%d %H:%M:%S"),
            "categories": ["developer", "documentation", "repo_manager", "research"],
            "tools": ["git", "file"]
        }

        with open(config_path, 'w') as f:
            yaml.dump(default_config, f, default_flow_style=False)
        return default_config

    console.print(f"✅ Found project config for '{project_path}' at {config_path}", style="green")

    # Load existing config from the found path
    try:
        with open(config_path, 'r') as f:
            config = yaml.safe_load(f)
        return config
    except Exception as e:
        console.print(f"❌ Error loading project config from {config_path}: {e}", style="red")
        return {
            "project_name": project_path.split('/')[-1],
            "description": "Error loading configuration",
            "repository": None
        }


def record_task_result(task_id: str, result: dict[str, any], learning_tokens: int):
    """
    Records the result of a DevOps task and updates the task queue.
    """
    try:
        task_manager.update_task_status(
            task_id=task_id,
            status="completed" if result.get("success") else "failed",
            result=result
        )
        # Logic to use learning_tokens, e.g., for model feedback
        logger.info(f"Recorded task result for {task_id} with {learning_tokens} learning tokens.")
    except Exception as e:
        logger.error(f"Error recording task result: {e}")


# Execute DevOps task
def execute_devops_task(router, args, project_config):
    """Execute the DevOps task with the given parameters."""
    log_stream = StringIO()
    original_stdout = sys.stdout

    try:
        start_time = time.time()
        task_id = args.task_id or str(uuid.uuid4())

        console.print(f"\n🚀 [bold blue]Executing DevOps Task[/bold blue]")
        console.print(f"📝 Task ID: [bold cyan]{task_id}[/bold cyan]")
        console.print(f"🔍 Category: [bold green]{args.category}[/bold green]")
        console.print(f"📂 Project: [bold yellow]{args.project}[/bold yellow]")

        # Redirect stdout to capture verbose logs
        if args.verbose:
            sys.stdout = log_stream

        result = run_ai_dev_ops_crew_securely(
            router=router,
            project_id=args.project,
            inputs={
                "prompt": args.prompt,
                "category": args.category,
                "repository": args.repo or project_config.get("repository"),
                "branch": args.branch or project_config.get("default_branch"),
                "verbose": bool(args.verbose),  # FIX: Ensure boolean value
                "dry_run": args.dry_run
            }
        )

        # Restore stdout
        sys.stdout = original_stdout

        if result and result.get("success"):
            console.print(f"\n✅ [bold green]DevOps Task completed successfully![/bold green]")
        else:
            console.print(f"\n❌ [bold red]DevOps Task failed.[/bold red]")

            # --- Start Diagnostic Crew ---
            console.print("\n🔬 [bold blue]Running Diagnostic Crew to analyze failure...[/bold blue]")

            # Get the captured log output
            full_log_output = log_stream.getvalue()

            # Collect real coworker names from the crew
            coworker_names = []
            if 'crew' in locals() and hasattr(crew, 'agents'):
                coworker_names = [agent.name for agent in crew.agents]
                console.print(f"DEBUG: Real coworker names from crew: {coworker_names}", style="blue")

            # --- NEW: Initialize loop detection ---
            loop_detector = LoopDetector(max_consecutive_repeats=3)

            def stop_on_loop_callback(output):
                # FIX: Extract string from ToolResult object
                if hasattr(output, 'result'):
                    output_string = output.result
                else:
                    output_string = str(output)

                if loop_detector.detect(output_string):
                    raise RuntimeError("Loop detected. Stopping diagnostic crew.")

            # Create the diagnostic agent and crew
            diagnostic_agent = create_diagnostic_agent(
                router=router,
                inputs={},
                coworker_names=coworker_names
            )
            diagnostic_task = Task(
                description=f"Analyze the following logs to diagnose the reason for a delegation failure:\n\n{full_log_output}",
                agent=diagnostic_agent,
                expected_output="A concise explanation of the delegation failure and potential fixes."
            )
            diagnostic_crew = Crew(
                agents=[diagnostic_agent],
                tasks=[diagnostic_task],
                verbose=bool(args.verbose),  # FIX: Ensure boolean value
                step_callback=stop_on_loop_callback  # NEW: Add the callback
            )

            # Run the diagnostic crew with a try-except block for the new loop detection
            try:
                diagnostic_result = diagnostic_crew.kickoff()
                console.print(f"\n🔬 [bold green]Diagnostic Agent Analysis:[/bold green]")
                console.print(diagnostic_result)
            except RuntimeError as e:
                console.print(f"\n❌ [bold red]Diagnostic Crew stopped due to detected loop: {e}[/bold red]")
            # --- End Diagnostic Crew ---

        end_time = time.time()
        record_task_result(
            task_id=task_id,
            model_used="multiple", peer_used="internal", start_time=start_time,
            end_time=end_time, success=result and result.get("success"),
            error_message=result.get("error") if result else "Unknown failure",
            git_changes=None, token_usage=None
        )

    except Exception as e:
        sys.stdout = original_stdout  # Ensure stdout is restored on error
        console.print(f"❌ An unexpected error occurred: {e}", style="red")
        logger.error(f"Error executing DevOps task: {e}\n{traceback.format_exc()}")
        end_time = time.time()
        record_task_result(
            task_id=task_id,
            model_used="multiple", peer_used="internal", start_time=start_time,
            end_time=end_time, success=False, error_message=str(e),
            git_changes=None, token_usage=None
        )


# Main entry point for the script
if __name__ == "__main__":
    parser = setup_arg_parser()
    args = parser.parse_args()
    router = get_router()
    project_config = load_project_config(args.project, project_root)
    execute_devops_task(router, args, project_config)

    try:
        # Load project config, which now uses the dynamic path and project_root
        project_config = load_project_config(args.project, project_root)

        # Use the repository from the config if not specified on the command line
        if not args.repo and project_config.get("repository"):
            args.repo = project_config.get("repository")

        # Use the branch from the config if not specified on the command line
        if not args.branch and project_config.get("default_branch"):
            args.branch = project_config.get("default_branch")

        # Initialize peer discovery and router
        discovery = PeerDiscovery()
        router = get_router()

        # Execute the task
        result = execute_devops_task(router, args, project_config)

        console.print("\n--- Final Result ---")
        # Check if result is not None before trying to call .get()
        if result and result.get("success"):
            console.print(result.get("message", "Success"), style="green")
            if result.get("result"):
                console.print(result["result"])
        else:
            # Provide a default error message if result is None
            error_message = result.get('error') if result else 'Unknown Error (Task function returned None)'
            console.print(f"Error: {error_message}", style="red")
            sys.exit(1)

    except Exception as e:
        console.print(f"\n❌ [bold red]Execution failed[/bold red]")
        console.print(f"Reason: {e}", style="red")
        logger.error(f"Execution failed: {e}", exc_info=True)
        sys.exit(1)


def run_ai_dev_ops_crew_securely(router, project_id, inputs) -> dict[str, Any]:
    """
    Securely run the AI DevOps Crew.
    """
    manager = AIOpsCrewManager(router, project_id, inputs)
    return manager.execute()
