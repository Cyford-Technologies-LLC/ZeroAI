#!/usr/bin/env python3
# run/internal/run_dev_ops.py
"""
AI DevOps Crew Runner

This script safely executes the internal DevOps AI crew for secure development operations.
It provides a secure command-line interface to trigger internal maintenance tasks.
"""

import sys
import os
import signal
from pathlib import Path

# Set CREW_TYPE for internal operations BEFORE any imports
os.environ["CREW_TYPE"] = "internal"

# Add the project root to the Python path FIRST to make imports work
project_root = Path(__file__).parent.parent.parent
sys.path.insert(0, str(project_root))

import argparse
import json
import time
import uuid
import logging
import traceback
from rich.console import Console
import yaml
from src.crews.internal.diagnostics.agents import create_diagnostic_agent
from crewai import Agent, Crew, Task, Process
from src.ai_dev_ops_crew import run_ai_dev_ops_crew_securely, AIOpsCrewManager
from io import StringIO
from ast import literal_eval
from typing import Dict, Any, List, Optional
from src.config import config
from src.distributed_router import DistributedRouter
from src.crews.internal.team_manager.agents import create_team_manager_agent, load_all_coworkers
from src.utils.loop_detection import LoopDetector  # Import the new class

from src.peer_discovery import PeerDiscovery

logger = logging.getLogger(__name__)

crew_type = os.getenv("CREW_TYPE")

from src.learning.task_manager import TaskManager

task_manager = TaskManager()

# Configure console for rich output
console = Console()

# Global flag for graceful shutdown
shutdown_requested = False

def signal_handler(signum, frame):
    """Handle Ctrl+C gracefully"""
    global shutdown_requested
    shutdown_requested = True
    console.print("\n\n🛑 [bold yellow]Shutdown requested. Cleaning up...[/bold yellow]")
    console.print("Press Ctrl+C again to force exit.", style="dim")
    
    # Set a second handler for force exit
    signal.signal(signal.SIGINT, lambda s, f: sys.exit(1))

# Register signal handler
signal.signal(signal.SIGINT, signal_handler)


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
        # Check for shutdown request at start
        if shutdown_requested:
            console.print("Shutdown requested. Aborting task execution.", style="yellow")
            return {"success": False, "error": "Task aborted by user"}
            
        start_time = time.time()
        task_id = args.task_id or str(uuid.uuid4())

        console.print(f"\n🚀 [bold blue]Executing DevOps Task[/bold blue]")
        console.print(f"📝 Task ID: [bold cyan]{task_id}[/bold cyan]")
        console.print(f"🔍 Category: [bold green]{args.category}[/bold green]")
        console.print(f"📂 Project: [bold yellow]{args.project}[/bold yellow]")

        # Prepare the inputs dictionary for run_ai_dev_ops_crew_securely
        task_inputs = {
            "prompt": args.prompt,
            "category": args.category,
            "repository": args.repo or project_config.get("repository"),
            "branch": args.branch or project_config.get("default_branch"),
            "verbose": args.verbose,
            "dry_run": args.dry_run,
            "task_id": task_id,
            "project_id": args.project,
        }

        # Redirect stdout to capture verbose logs
        if args.verbose:
            sys.stdout = log_stream

        # Check for shutdown request before crew execution
        if shutdown_requested:
            console.print("Shutdown requested. Aborting crew execution.", style="yellow")
            return {"success": False, "error": "Crew execution aborted by user"}
            
        # Call the new entry point, which now handles manager and crew setup
        result = run_ai_dev_ops_crew_securely(
            router=router,
            project_id=args.project,
            inputs=task_inputs
        )

        # Restore stdout
        sys.stdout = original_stdout

        if result and result.get("success"):
            console.print(f"\n✅ [bold green]DevOps Task completed successfully![/bold green]")
            if result.get("result"):
                console.print(result["result"])
        else:
            console.print(f"\n❌ [bold red]DevOps Task failed.[/bold red]")

            # Handle diagnostics after task failure
            console.print("\n🔬 [bold blue]Running Diagnostic Crew to analyze failure...[/bold blue]")

            full_log_output = log_stream.getvalue()

            # Placeholder for diagnostic logic:
            console.print(f"Diagnostic report based on log output:\n{full_log_output}", style="yellow")
            if result and 'error' in result:
                 console.print(f"Error from result: {result['error']}", style="red")

        return result

    except KeyboardInterrupt:
        sys.stdout = original_stdout
        console.print("\n\n🛑 [bold yellow]Task execution interrupted by user[/bold yellow]")
        return {"success": False, "error": "Task interrupted by user"}
    except Exception as e:
        sys.stdout = original_stdout
        console.print(f"\n❌ [bold red]An unexpected error occurred during DevOps task execution: {e}[/bold red]")
        console.print(traceback.format_exc(), style="red")
        return {"success": False, "error": str(e)}


def run_ai_dev_ops_crew_securely(router, project_id, inputs) -> dict[str, Any]:
    """
    Securely run the AI DevOps Crew.
    """
    manager = AIOpsCrewManager(router, project_id, inputs)
    return manager.execute()


# Main entry point for the script
if __name__ == "__main__":
    try:
        parser = setup_arg_parser()
        args = parser.parse_args()
        
        # Check for shutdown request
        if shutdown_requested:
            console.print("Shutdown requested during startup. Exiting.", style="yellow")
            sys.exit(0)
        
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
        router = DistributedRouter(discovery)

        # Execute the task
        if shutdown_requested:
            console.print("Shutdown requested before task execution. Exiting.", style="yellow")
            sys.exit(0)
            
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
            console.print(f"Error: {error_message}", style="red", markup=False)
            sys.exit(1)

    except KeyboardInterrupt:
        console.print("\n\n🛑 [bold yellow]Task interrupted by user[/bold yellow]")
        console.print("Exiting gracefully...", style="dim")
        sys.exit(0)
    except Exception as e:
        console.print(f"\n❌ [bold red]Execution failed[/bold red]")
        console.print(f"Reason: {str(e)}", style="red", markup=False)
        sys.exit(1)