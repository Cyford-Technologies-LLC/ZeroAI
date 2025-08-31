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

# Add the project root and internal crews path to sys.path
project_root = Path(__file__).parent.parent.parent
sys.path.insert(0, str(project_root))
internal_crews_path = project_root / "src" / "crews" / "internal"
sys.path.insert(0, str(internal_crews_path))

console = Console()

# Helper function to ensure directory exists
def ensure_dir_exists(directory_path):
    """Ensure that a directory exists, creating it if necessary."""
    if isinstance(directory_path, str):
        directory_path = Path(directory_path)
    directory_path.mkdir(parents=True, exist_ok=True)
    return directory_path

# Import required modules with error tracking
console.print("\n🔍 Starting import process...")

try:
    from src.peer_discovery import PeerDiscovery
    from src.devops_router import get_router
    from src.utils.yaml_utils import load_yaml_config
    # FIX: Import ai_dev_ops_crew and ErrorLogger correctly
    from src.ai_dev_ops_crew import run_ai_dev_ops_crew_securely
    from src.crews.internal.team_manager.agents import ErrorLogger
    console.print("✅ Successfully imported core modules.")

    # Try to import learning components, create dummy if not available
    try:
        from src.learning import record_task_result
        console.print("✅ Successfully imported record_task_result")
    except ImportError:
        console.print("⚠️ Learning module not found. Task result recording skipped.", style="yellow")
        def record_task_result(*args, **kwargs):
            console.print("ℹ️ Task result recording skipped (learning module not available)", style="yellow")
            return True

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

# FIX: Pass ErrorLogger to other functions
error_logger = ErrorLogger()

def setup_arg_parser():
    """Set up and return the argument parser."""
    parser = argparse.ArgumentParser(description="Run the AI DevOps Crew")
    parser.add_argument("prompt", help="The task description or prompt")
    parser.add_argument("--project", default="default", help="Project identifier")
    parser.add_argument("--category", default="general", help="Task category")
    parser.add_argument("--task-id", default=None, help="Task ID for tracking")
    parser.add_argument("--repo", default=None, help="Git repository URL")
    parser.add_argument("--branch", default=None, help="Git branch name")
    parser.add_argument("--verbose", "-v", action="store_true", help="Enable verbose output")
    parser.add_argument("--dry-run", action="store_true", help="Only simulate execution")
    return parser

def load_project_config(project_path: str, project_root: Path) -> dict:
    """Load project configuration from YAML file, supporting nested directories."""
    config_dir_root = project_root / "knowledge" / "internal_crew"
    config_path = config_dir_root / project_path / "project_config.yaml"
    config_dir = config_path.parent

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
# Continues from Part 1...

def execute_devops_task(router, args, project_config):
    """Execute the DevOps task with the given parameters."""
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
    if not ai_dev_ops_crew_path.exists():
        console.print(f"❌ ai_dev_ops_crew.py not found at {ai_dev_ops_crew_path}", style="red")
        return {"success": False, "error": f"ai_dev_ops_crew.py not found"}
    console.print(f"✅ Found ai_dev_ops_crew.py at {ai_dev_ops_crew_path}")


    inputs = {
        "prompt": args.prompt,
        "category": args.category,
        "repository": args.repo or project_config.get("repository"),
        "branch": args.branch or project_config.get("default_branch", "main"),
        "task_id": task_id,
        "verbose": args.verbose,
        # FIX: Pass error_logger instance
        "error_logger": error_logger
    }

    try:
        result = run_ai_dev_ops_crew_securely(router=router, project_id=args.project, inputs=inputs)
    except Exception as e:
        console.print(f"❌ Error during crew execution: {e}", style="red")
        console.print("Traceback:", style="red")
        console.print(traceback.format_exc())
        result = {"success": False, "error": str(e)}

    end_time = time.time()
    success = result.get("success", False)
    error_message = result.get("error") if not success else None

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

if __name__ == "__main__":
    parser = setup_arg_parser()
    args = parser.parse_args()

    try:
        project_config = load_project_config(args.project, project_root)

        if not args.repo and project_config.get("repository"):
            args.repo = project_config.get("repository")

        if not args.branch and project_config.get("default_branch"):
            args.branch = project_config.get("default_branch")

        discovery = PeerDiscovery()
        router = get_router()

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
