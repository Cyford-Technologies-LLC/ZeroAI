# src/ai_dev_ops_crew.py

import os
import sys
import uuid
import time
import logging
import importlib
import traceback
import yaml
from io import StringIO
from pathlib import Path
from typing import Dict, Any, Optional, List
from rich.console import Console
from rich.table import Table

from crewai import Crew, Task, Process

# Import the team manager crew and agent creation functions
from src.crews.internal.team_manager.crew import get_team_manager_crew
from src.crews.internal.diagnostics.agents import create_diagnostic_agent
from src.crews.internal.team_manager.agents import ErrorLogger, load_all_coworkers

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)
console = Console()


def preload_internal_crews() -> Dict[str, Dict[str, Any]]:
    """
    Preload all internal crew modules and check which ones are available.
    Returns:
        Dictionary with crew status information
    """
    crew_status = {}
    error_logger = None

    try:
        from src.crews.internal.team_manager.agents import ErrorLogger
        error_logger = ErrorLogger()
    except ImportError as e:
        console.print(f"⚠️ Could not import ErrorLogger: {e}", style="yellow")

    internal_crews_dir = Path("src/crews/internal")

    if not internal_crews_dir.exists():
        error_msg = f"Internal crews directory not found at {internal_crews_dir}"
        console.print(f"❌ {error_msg}", style="red")
        if error_logger:
            error_logger.log_error(error_msg, {})
        return {"error": error_msg}

    table = Table(title="Internal Crews Status")
    table.add_column("Crew", style="cyan")
    table.add_column("Status", style="white")
    table.add_column("Details", style="white")
    table.add_column("Files", style="dim")

    crew_dirs = [d for d in internal_crews_dir.iterdir() if
                 d.is_dir() and not d.name.startswith("__") and d.name not in ["tools", "diagnostics"]]

    console.print(f"🔍 [bold blue]Checking internal crews availability[/bold blue]")
    console.print(f"Found {len(crew_dirs)} potential internal crews", style="blue")

    for crew_dir in crew_dirs:
        crew_name = crew_dir.name
        crew_status[crew_name] = {
            "status": "unknown",
            "error": None,
            "files_present": [],
            "directory": str(crew_dir),
            "agents": []
        }

        required_files = ["__init__.py", "agents.py", "tasks.py", "crew.py"]
        missing_files = []

        for file in required_files:
            if (crew_dir / file).exists():
                crew_status[crew_name]["files_present"].append(file)
            else:
                missing_files.append(file)

        if missing_files:
            crew_status[crew_name]["status"] = "incomplete"
            crew_status[crew_name]["error"] = f"Missing files: {', '.join(missing_files)}"
            table.add_row(
                crew_name,
                "⚠️ Incomplete",
                f"Missing: {', '.join(missing_files)}",
                ", ".join(crew_status[crew_name]["files_present"])
            )
            continue

        try:
            import_path = f"src.crews.internal.{crew_name}.crew"
            module = importlib.import_module(import_path)

            agents_import_path = f"src.crews.internal.{crew_name}.agents"
            agents_module = importlib.import_module(agents_import_path)

            crew_status[crew_name]["status"] = "available"
            crew_status[crew_name]["module"] = import_path

            get_crew_func = f"get_{crew_name}_crew"
            if hasattr(module, get_crew_func):
                crew_status[crew_name]["get_crew_function"] = get_crew_func

            for func_name in dir(agents_module):
                if func_name.startswith("create_") and func_name.endswith("_agent"):
                    crew_status[crew_name]["agents"].append(func_name)

            if not crew_status[crew_name]["agents"]:
                crew_status[crew_name]["status"] = "incomplete"
                crew_status[crew_name]["error"] = "No agent creator functions found."
                table.add_row(
                    crew_name,
                    "⚠️ Incomplete",
                    "No agent creator functions found",
                    ", ".join(required_files)
                )
                continue

            table.add_row(
                crew_name,
                "✅ Available",
                f"Found {len(crew_status[crew_name]['agents'])} agents",
                ", ".join(required_files)
            )

        except ImportError as e:
            crew_status[crew_name]["status"] = "import_error"
            crew_status[crew_name]["error"] = str(e)
            table.add_row(
                crew_name,
                "❌ Import Error",
                str(e),
                ", ".join(crew_status[crew_name]["files_present"])
            )
            if error_logger:
                error_logger.log_error(
                    f"Failed to import {crew_name} crew: {str(e)}",
                    {"crew_name": crew_name, "traceback": traceback.format_exc()}
                )

        except Exception as e:
            crew_status[crew_name]["status"] = "error"
            crew_status[crew_name]["error"] = str(e)
            table.add_row(
                crew_name,
                "❌ Error",
                str(e),
                ", ".join(crew_status[crew_name]["files_present"])
            )
            if error_logger:
                error_logger.log_error(
                    f"Error with {crew_name} crew: {str(e)}",
                    {"crew_name": crew_name, "traceback": traceback.format_exc()}
                )

    console.print(table)

    for crew_name, info in crew_status.items():
        status_style = "green" if info["status"] == "available" else "yellow" if info[
                                                                                     "status"] == "incomplete" else "red"
        console.print(f"[bold]{crew_name}[/bold]: [{status_style}]{info['status']}[/{status_style}]")
        if info["error"]:
            console.print(f"  Error: {info['error']}")

    return crew_status


class AIOpsCrewManager:
    """
    Manager for the AI DevOps Crew.
    Orchestrates secure execution of internal development and maintenance tasks
    by delegating to specialized sub-crews.
    """

    def __init__(self, router, project_id, inputs, project_root_path):
        """
        Initialize the AIOps Crew Manager.
        """
        self.router = router
        self.project_id = project_id
        self.inputs = inputs
        self.project_root_path = project_root_path
        self.task_id = inputs.get("task_id", str(uuid.uuid4()))
        self.prompt = inputs.get("prompt", "")
        self.category = inputs.get("category", "general")
        self.repository = inputs.get("repository")
        self.branch = inputs.get("branch", "main")

        self.model_used = "unknown"
        self.peer_used = "unknown"
        self.token_usage = {"total_tokens": 0}
        self.base_url = None

        self.crews_status = inputs.get("crews_status", {})
        if not self.crews_status:
            console.print("Preloading internal crews status...", style="blue")
            self.crews_status = preload_internal_crews()

        self.project_config = self._load_project_config()
        self.working_dir = self._setup_working_dir()
        self.tools = self._initialize_tools()

    def _load_project_config(self) -> Dict[str, Any]:
        """Load the project configuration from YAML file."""
        from src.utils.yaml_utils import load_yaml_config
        from src.dev_ops_crew_runner import ensure_dir_exists

        config_dir_root = self.project_root_path / "knowledge" / "internal_crew"
        config_path = config_dir_root / self.project_id / "project_config.yaml"
        config_dir = config_path.parent

        if not config_path.exists():
            console.print(f"⚠️ No config found for project at '{config_path}', creating default", style="yellow")
            ensure_dir_exists(config_dir)

            default_config = {
                "project_name": self.project_id.split('/')[-1],
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

        console.print(f"✅ Found project config for '{self.project_id}' at {config_path}", style="green")
        try:
            return load_yaml_config(config_path)
        except Exception as e:
            console.print(f"❌ Error loading project config from {config_path}: {e}", style="red")
            return {"project_name": self.project_id.split('/')[-1], "description": "Error loading configuration",
                    "repository": None}

    def _setup_working_dir(self) -> Path:
        """Set up the working directory for the crew."""
        working_dir = self.project_root_path / "workspaces" / self.project_id
        if not working_dir.exists():
            working_dir.mkdir(parents=True)
            console.print(f"📁 Created working directory: {working_dir}", style="dim")
        return working_dir

    def _initialize_tools(self) -> List[Any]:
        """Initialize the tools based on the project configuration."""
        from src.crews.internal.tools.git_tool import GitTool, FileTool
        tools = []
        try:
            git_tool = GitTool(working_dir=str(self.working_dir))
            file_tool = FileTool(working_dir=str(self.working_dir))
            tools = [git_tool, file_tool]
            console.print("✅ Initialized tools for crews", style="green")
        except Exception as e:
            console.print(f"❌ Error initializing tools: {e}", style="red")
            ErrorLogger().log_error(f"Error initializing tools: {str(e)}", {"project_id": self.project_id})
        return tools

    def run_ai_dev_ops_crew_securely(self, verbose: bool, dry_run: bool) -> Dict[str, Any]:
        """
        Main entry point for running the AI DevOps Crew.
        """
        if dry_run:
            console.print("\n🧪 [bold yellow]DRY RUN - No crew will be executed[/bold yellow]")
            return {"success": True, "message": "Dry run completed."}

        try:
            crew = get_team_manager_crew(
                router=self.router,
                tools=self.tools,
                project_id=self.project_id,
                working_dir=self.working_dir,
                inputs=self.inputs,
                crews_status=self.crews_status
            )

            if crew is None:
                error_msg = "❌ Error: Crew not created because no worker agents were found."
                console.print(error_msg, style="red")
                return {"success": False, "error": error_msg}

            result = crew.kickoff(inputs={'prompt': self.prompt})

            if verbose:
                console.print(f"\nFinal Result:\n{result}")

            return {"success": True, "result": result}
        except Exception as e:
            error_logger = ErrorLogger()
            error_logger.log_error(
                f"Error executing AI DevOps crew: {str(e)}",
                {
                    "project_id": self.project_id,
                    "task_id": self.task_id,
                    "traceback": traceback.format_exc(),
                }
            )
            return {"success": False, "error": str(e), "traceback": traceback.format_exc()}


def run_ai_dev_ops_crew_securely(prompt: str, project_id: str, category: str, repository: str, branch: str,
                                 verbose: bool, dry_run: bool, router: Any) -> Dict[str, Any]:
    """
    Convenience function to run the AI DevOps crew with provided parameters.
    This serves as the main entry point for the runner script.
    """
    project_root = Path(__file__).parent.parent
    inputs = {
        "prompt": prompt,
        "project": project_id,
        "category": category,
        "repository": repository,
        "branch": branch,
        "verbose": verbose,
        "dry_run": dry_run,
    }

    manager = AIOpsCrewManager(router=router, project_id=project_id, inputs=inputs, project_root_path=project_root)

    return manager.run_ai_dev_ops_crew_securely(verbose, dry_run)


if __name__ == "__main__":
    console.print("This module should be imported, not run directly.", style="red")
    sys.exit(1)

