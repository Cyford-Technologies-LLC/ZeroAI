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

# Important: for any crews outside the default, make sure the proper crews are loaded
os.environ["CREW_TYPE"] = "internal"

# Add the parent directory to the Python path to make imports work
project_root = Path(__file__).parent.parent.parent
sys.path.insert(0, str(project_root))

# Add the internal crews path to sys.path to ensure we can import from there
internal_crews_path = project_root / "src" / "crews" / "internal"
sys.path.insert(0, str(internal_crews_path))

# Configure console for rich output
console = Console()

# Add debug information to help diagnose import issues
console.print(f"Python path: {sys.path}")
console.print(f"Current directory: {os.getcwd()}")

# Helper function to ensure directory exists (since it's missing from yaml_utils)
def ensure_dir_exists(directory_path):
    """Ensure that a directory exists, creating it if necessary."""
    if isinstance(directory_path, str):
        directory_path = Path(directory_path)

    directory_path.mkdir(parents=True, exist_ok=True)
    return directory_path

# Import required modules with detailed error tracking
console.print("\n🔍 Starting import process with detailed debugging...")

try:
    # Try individual imports to isolate where the failure is happening
    console.print("Importing PeerDiscovery...")
    from src.peer_discovery import PeerDiscovery
    console.print("✅ Successfully imported PeerDiscovery")

    console.print("Importing get_router...")
    from src.devops_router import get_router
    console.print("✅ Successfully imported get_router")

    console.print("Importing load_yaml_config...")
    from src.utils.yaml_utils import load_yaml_config
    console.print("✅ Successfully imported load_yaml_config")

    # Try to import learning components
    try:
        console.print("Importing record_task_result...")
        from src.learning import record_task_result
        console.print("✅ Successfully imported record_task_result")
    except ImportError as e:
        console.print(f"⚠️ Learning module not found: {e}", style="yellow")
        console.print("Traceback:", style="yellow")
        console.print(traceback.format_exc())

        # Create a dummy record_task_result function
        def record_task_result(*args, **kwargs):
            console.print("ℹ️ Task result recording skipped (learning module not available)", style="yellow")
            return True

    # Import the ai_dev_ops_crew module now that paths are set
    console.print("Importing ai_dev_ops_crew...")
    from src.ai_dev_ops_crew import run_ai_dev_ops_crew_securely
    console.print("✅ Successfully imported ai_dev_ops_crew")

except ImportError as e:
    console.print(f"Failed to import required modules: {e}", style="red")
    console.print("Make sure you're running from the project root directory.")
    console.print("Detailed error:", style="red")
    console.print(traceback.format_exc())
    sys.exit(1)

# Configure logging
log_dir = Path("logs")
log_dir.mkdir(exist_ok=True)
logging.basicConfig(
    filename=log_dir / "dev_ops.log",
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

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
                       help="Task ID for tracking (auto-generated if not provided)")
    parser.add_argument("--repo", default=None,
                       help="Git repository URL")
    parser.add_argument("--branch", default=None,
                       help="Git branch name")
    parser.add_argument("--verbose", "-v", action="store_true",
                       help="Enable verbose output")
    parser.add_argument("--dry-run", action="store_true",
                       help="Only simulate execution without making changes")

    return parser

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
            "project_name": project_path.split('/')[-1], # Extract project name from path
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
        config = load_yaml_config(config_path)
        return config
    except Exception as e:
        console.print(f"❌ Error loading project config from {config_path}: {e}", style="red")
        return {
            "project_name": project_path.split('/')[-1],
            "description": "Error loading configuration",
            "repository": None
        }

def execute_devops_task(router, args, project_config):
    """Execute the DevOps task with the given parameters."""
    try:
        start_time = time.time()
        task_id = args.task_id or str(uuid.uuid4())

        console.print(f"\n🚀 [bold blue]Executing DevOps Task[/bold blue]")
        console.print(f"📝 Task ID: [bold cyan]{task_id}[/bold cyan]")
        console.print(f"🔍 Category: [bold green]{args.category}[/bold green]")
        console.print(f"📂 Project: [bold yellow]{args.project}[/bold yellow]")

        if args.verbose:
            console.print(f"📋 Task details:")
            console.print(f"   Prompt: {args.prompt}")
            if args.repo:
                console.print(f"   Repository: {args.repo}")
            if args.branch:
                console.print(f"   Branch: {args.branch}")

        if args.dry_run:
            console.print("\n🧪 [bold yellow]DRY RUN - No changes will be made[/bold yellow]")
            end_time = time.time()
            record_task_result(
                task_id=task_id, prompt=args.prompt, category=args.category,
                model_used="dry_run", peer_used="local", start_time=start_time,
                end_time=end_time, success=True, error_message=None,
                git_changes=None, token_usage=None
            )
            return {"success": True, "message": "Dry run completed"}

        console.print("\n⚙️ [bold blue]Processing task...[/bold blue]")

        ai_dev_ops_crew_path = project_root / "src" / "ai_dev_ops_crew.py"
        if ai_dev_ops_crew_path.exists():
            console.print(f"✅ Found ai_dev_ops_crew.py at {ai_dev_ops_crew_path}")
        else:
            console.print(f"❌ ai_dev_ops_crew.py not found at {ai_dev_ops_crew_path}", style="red")
            return {"success": False, "error": f"ai_dev_ops_crew.py not found"}

        # Create the inputs dictionary
        inputs = {
            "prompt": args.prompt,
            "category": args.category,
            "repository": args.repo or project_config.get("repository"),
            "branch": args.branch or project_config.get("default_branch", "main"),
            "task_id": task_id,
            "verbose": args.verbose
        }

        try:
            result = run_ai_dev_ops_crew_securely(router=router, project_id=args.project, inputs=inputs)
        except Exception as e:
            console.print(f"❌ Error during crew execution: {e}", style="red")
            traceback_str = traceback.format_exc()
            console.print("Traceback:", style="red")
            console.print(traceback_str)
            result = {"success": False, "error": str(e)}

        end_time = time.time()
        success = result.get("success", False)
        error_message = result.get("error") if not success else None

        # Log and record the task result
        if success:
            console.print(f"✅ [bold green]Task completed successfully in {end_time - start_time:.2f} seconds[/bold green]")
        else:
            console.print(f"❌ [bold red]Task failed after {end_time - start_time:.2f} seconds[/bold red]")
            console.print(f"Error: {error_message}", style="red")

        record_task_result(
            task_id=task_id,
            prompt=args.prompt,
            category=args.category,
            model_used=result.get("model_used", "n/a"),
            peer_used=result.get("peer_used", "n/a"),
            start_time=start_time,
            end_time=end_time,
            success=success,
            error_message=error_message,
            git_changes=None,
            token_usage=result.get("token_usage")
        )

        return result

    except Exception as e:
        console.print(f"❌ An unexpected error occurred: {e}", style="red")
        logger.error(f"Unexpected error in execute_devops_task: {e}", exc_info=True)
        return {"success": False, "error": str(e)}

if __name__ == "__main__":
    parser = setup_arg_parser()
    args = parser.parse_args()

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
        # FIX: Remove the verbose=args.verbose argument
        router = get_router(discovery)

        # Execute the task
        result = execute_devops_task(router, args, project_config)

        console.print("\n--- Final Result ---")
        if result.get("success"):
            console.print(result.get("message", "Success"), style="green")
            if result.get("result"):
                console.print(result["result"])
        else:
            console.print(f"Error: {result.get('error')}", style="red")
            sys.exit(1)

    except Exception as e:
        console.print(f"\n❌ [bold red]Execution failed[/bold red]")
        console.print(f"Reason: {e}", style="red")
        logger.error(f"Execution failed: {e}", exc_info=True)
        sys.exit(1)
