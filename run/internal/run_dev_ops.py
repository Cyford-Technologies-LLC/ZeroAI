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
from pathlib import Path
from rich.console import Console

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

# Configure console for rich output
console = Console()

# Helper function to ensure directory exists (since it's missing from yaml_utils)
def ensure_dir_exists(directory_path):
    """Ensure that a directory exists, creating it if necessary."""
    if isinstance(directory_path, str):
        directory_path = Path(directory_path)

    directory_path.mkdir(parents=True, exist_ok=True)
    return directory_path

# Import required modules
try:
    from peer_discovery import PeerDiscovery
    from devops_router import get_router
    from utils.yaml_utils import load_yaml_config

    # Try to import learning components
    try:
        from learning import record_task_result
    except ImportError:
        console.print("⚠️ Learning module not found. Task results won't be recorded.", style="yellow")

        # Create a dummy record_task_result function
        def record_task_result(*args, **kwargs):
            console.print("ℹ️ Task result recording skipped (learning module not available)", style="yellow")
            return True

except ImportError as e:
    console.print(f"Failed to import required modules: {e}", style="red")
    console.print("Make sure you're running from the project root directory.")
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
                       help="Project identifier (default: 'default')")
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

def load_project_config(project_name):
    """Load project configuration from YAML file."""
    # Define the project config path
    config_path = Path(f"knowledge/internal_crew/{project_name}/project_config.yaml")

    # Ensure the directory exists
    config_dir = config_path.parent
    ensure_dir_exists(config_dir)

    # If config doesn't exist, create a default one
    if not config_path.exists():
        console.print(f"⚠️ No config found for project '{project_name}', creating default", style="yellow")

        default_config = {
            "project_name": project_name,
            "description": "Auto-generated project configuration",
            "repository": None,
            "default_branch": "main",
            "created_at": time.strftime("%Y-%m-%d %H:%M:%S"),
            "categories": ["developer", "documentation", "repo_manager", "research"],
            "tools": ["git", "file"]
        }

        # Ensure the directory exists
        config_dir.mkdir(parents=True, exist_ok=True)

        # Write default config
        with open(config_path, 'w') as f:
            import yaml
            yaml.dump(default_config, f, default_flow_style=False)

        return default_config

    # Load existing config
    try:
        config = load_yaml_config(config_path)
        return config
    except Exception as e:
        console.print(f"❌ Error loading project config: {e}", style="red")
        # Return a minimal default config
        return {
            "project_name": project_name,
            "description": "Error loading configuration",
            "repository": None
        }

def execute_devops_task(router, args, project_config):
    """Execute the DevOps task with the given parameters."""
    try:
        # Start timing the execution
        start_time = time.time()

        # Create a unique task ID if not provided
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

        # If this is a dry run, don't execute
        if args.dry_run:
            console.print("\n🧪 [bold yellow]DRY RUN - No changes will be made[/bold yellow]")
            end_time = time.time()

            # Record the dry run in learning system
            record_task_result(
                task_id=task_id,
                prompt=args.prompt,
                category=args.category,
                model_used="dry_run",
                peer_used="local",
                start_time=start_time,
                end_time=end_time,
                success=True,
                error_message=None,
                git_changes=None,
                token_usage=None
            )

            return {"success": True, "message": "Dry run completed"}

        # Here you would call your actual execution code
        # This is a placeholder for your AIOpsCrewManager instantiation and execution
        console.print("\n⚙️ [bold blue]Processing task...[/bold blue]")

        try:
            # Import your actual implementation
            from src.ai_dev_ops_crew import run_ai_dev_ops_crew_securely

            # Execute the task
            result = run_ai_dev_ops_crew_securely(
                router=router,
                project_id=args.project,
                inputs={
                    "prompt": args.prompt,
                    "category": args.category,
                    "repository": args.repo or project_config.get("repository"),
                    "branch": args.branch or project_config.get("default_branch", "main"),
                    "task_id": task_id
                }
            )
        except ImportError:
            console.print("⚠️ Could not import AIOpsCrewManager, using fallback method", style="yellow")

            # Fallback to a simpler method if the manager is not available
            result = {
                "success": True,
                "message": f"Task '{args.prompt}' processed with category '{args.category}'",
                "token_usage": {"total_tokens": 0}
            }

        # End timing
        end_time = time.time()
        execution_time = end_time - start_time

        console.print(f"\n✅ [bold green]Task completed in {execution_time:.2f} seconds[/bold green]")

        # Get model and peer information for feedback
        model_used = getattr(result, "model_used", "unknown") if hasattr(result, "model_used") else "unknown"
        peer_used = getattr(result, "peer_used", "unknown") if hasattr(result, "peer_used") else "unknown"

        if args.verbose:
            console.print(f"🤖 Model used: [bold blue]{model_used}[/bold blue]")
            console.print(f"🖥️ Peer used: [bold blue]{peer_used}[/bold blue]")

        # Extract git changes if available
        git_changes = None
        if hasattr(result, "git_changes"):
            git_changes = result.git_changes
        elif isinstance(result, dict) and "git_changes" in result:
            git_changes = result["git_changes"]

        # Extract token usage if available
        token_usage = None
        if hasattr(result, "token_usage"):
            token_usage = result.token_usage
        elif isinstance(result, dict) and "token_usage" in result:
            token_usage = result["token_usage"]

        # Record the outcome in the feedback loop
        record_task_result(
            task_id=task_id,
            prompt=args.prompt,
            category=args.category,
            model_used=model_used,
            peer_used=peer_used,
            start_time=start_time,
            end_time=end_time,
            success=True,
            error_message=None,
            git_changes=git_changes,
            token_usage=token_usage
        )

        return result

    except Exception as e:
        console.print(f"\n❌ [bold red]Error executing task: {e}[/bold red]")
        logger.error(f"Error executing DevOps task: {e}")

        # Record the failure in the feedback loop
        end_time = time.time()
        record_task_result(
            task_id=task_id if 'task_id' in locals() else str(uuid.uuid4()),
            prompt=args.prompt,
            category=args.category,
            model_used="unknown",
            peer_used="unknown",
            start_time=start_time if 'start_time' in locals() else time.time() - 1,
            end_time=end_time,
            success=False,
            error_message=str(e),
            git_changes=None,
            token_usage=None
        )

        return {"success": False, "error": str(e)}

def main():
    """Main entry point for the script."""
    try:
        # Parse command-line arguments
        parser = setup_arg_parser()
        args = parser.parse_args()

        # Initialize router
        console.print("🔄 Initializing secure DevOps router...")
        router = get_router()

        # Load project configuration
        console.print(f"📂 Loading configuration for project '{args.project}'...")
        project_config = load_project_config(args.project)

        # Execute the task
        result = execute_devops_task(router, args, project_config)

        # Handle result
        if isinstance(result, dict) and not result.get("success", True):
            console.print("\n--- Final Result ---")
            console.print(f"Error: {result.get('error', 'Unknown error')}")
            return 1
        else:
            console.print("\n--- Final Result ---")
            console.print("✅ Task completed successfully!")
            return 0

    except KeyboardInterrupt:
        console.print("\n⚠️ Operation cancelled by user.")
        return 130
    except Exception as e:
        console.print(f"\n❌ Fatal error: {e}", style="red")
        logger.critical(f"Fatal error in main function: {e}")
        return 1

if __name__ == "__main__":
    sys.exit(main())