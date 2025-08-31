import os
import sys
import uuid
import time
import logging
import importlib
import traceback
from pathlib import Path
from typing import Dict, Any, Optional, List
from rich.console import Console
from rich.table import Table

# --- FIX: Import ErrorLogger correctly ---
from src.crews.internal.team_manager.agents import ErrorLogger
from src.crews.internal.team_manager.crew import get_team_manager_crew

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)
console = Console()

def preload_internal_crews(error_logger: ErrorLogger) -> Dict[str, Dict[str, Any]]:
    """
    Preload all internal crew modules and check which ones are available.
    """
    crew_status = {}
    internal_crews_dir = Path("src/crews/internal")

    if not internal_crews_dir.exists():
        error_msg = f"Internal crews directory not found at {internal_crews_dir}"
        console.print(f"❌ {error_msg}", style="red")
        error_logger.log_error(error_msg, {})
        return {"error": error_msg}

    table = Table(title="Internal Crews Status")
    table.add_column("Crew", style="cyan")
    table.add_column("Status", style="white")
    table.add_column("Details", style="white")
    table.add_column("Files", style="dim")

    crew_dirs = [d for d in internal_crews_dir.iterdir() if d.is_dir() and not d.name.startswith("__")]
    console.print(f"🔍 [bold blue]Checking internal crews availability[/bold blue]")
    console.print(f"Found {len(crew_dirs)} potential internal crews", style="blue")

    for crew_dir in crew_dirs:
        crew_name = crew_dir.name
        crew_status[crew_name] = {
            "status": "unknown",
            "error": None,
            "files_present": [],
            "directory": str(crew_dir)
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
            table.add_row(crew_name, "⚠️ Incomplete", f"Missing: {', '.join(missing_files)}", ", ".join(crew_status[crew_name]["files_present"]))
            continue

        try:
            import_path = f"src.crews.internal.{crew_name}.crew"
            module = importlib.import_module(import_path)
            crew_status[crew_name]["status"] = "available"
            crew_status[crew_name]["module"] = import_path
            get_crew_func = f"get_{crew_name}_crew"
            if hasattr(module, get_crew_func):
                crew_status[crew_name]["get_crew_function"] = get_crew_func
                table.add_row(crew_name, "✅ Available", f"Found {get_crew_func}()", ", ".join(required_files))
            else:
                crew_status[crew_name]["error"] = f"Missing {get_crew_func}() function"
                crew_status[crew_name]["status"] = "incomplete"
                table.add_row(crew_name, "⚠️ Function Missing", f"Missing {get_crew_func}()", ", ".join(required_files))
        except ImportError as e:
            crew_status[crew_name]["status"] = "import_error"
            crew_status[crew_name]["error"] = str(e)
            table.add_row(crew_name, "❌ Import Error", str(e), ", ".join(crew_status[crew_name]["files_present"]))
            error_logger.log_error(f"Failed to import {crew_name} crew: {str(e)}", {"crew_name": crew_name, "traceback": traceback.format_exc()})
        except Exception as e:
            crew_status[crew_name]["status"] = "error"
            crew_status[crew_name]["error"] = str(e)
            table.add_row(crew_name, "❌ Error", str(e), ", ".join(crew_status[crew_name]["files_present"]))
            error_logger.log_error(f"Error with {crew_name} crew: {str(e)}", {"crew_name": crew_name, "traceback": traceback.format_exc()})

    console.print(table)
    for crew_name, info in crew_status.items():
        status_style = "green" if info["status"] == "available" else "yellow" if info["status"] == "incomplete" else "red"
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
    def __init__(self, router, project_id, inputs):
        self.router = router
        self.project_id = project_id
        self.inputs = inputs
        self.task_id = inputs.get("task_id", str(uuid.uuid4()))
        self.prompt = inputs.get("prompt", "")
        self.category = inputs.get("category", "general")
        self.repository = inputs.get("repository")
        self.branch = inputs.get("branch", "main")
        self.error_logger = ErrorLogger()
        self.model_used = "unknown"
        self.peer_used = "unknown"
        self.token_usage = {"total_tokens": 0}
        self.base_url = None
        self.crews_status = inputs.get("crews_status", {})
        if not self.crews_status:
            console.print("Preloading internal crews status...", style="blue")
            self.crews_status = preload_internal_crews(self.error_logger)
        self.project_config = self._load_project_config()
        self.working_dir = self._setup_working_dir()
        self.tools = self._initialize_tools()

    def _load_project_config(self) -> Dict[str, Any]:
        try:
            from src.utils.yaml_utils import load_yaml_config
            config_path = Path(f"knowledge/internal_crew/{self.project_id}/project_config.yaml")
            if not config_path.exists():
                console.print(f"⚠️ No config found for project '{self.project_id}', using default", style="yellow")
                return {
                    "project": {"name": self.project_id},
                    "crewai_settings": {"working_directory": f"/tmp/internal_crew/{self.project_id}"}
                }
            console.print(f"✅ Found project config at {config_path}", style="green")
            config = load_yaml_config(config_path)
            return config
        except Exception as e:
            console.print(f"❌ Error loading project config from {config_path}: {e}", style="red")
            return {"error": str(e)}

    def _setup_working_dir(self):
        # Implementation of _setup_working_dir...
        pass

    def _initialize_tools(self):
        # Implementation of _initialize_tools...
        pass

    def run(self):
        # Implementation of the run method...
        pass

def run_ai_dev_ops_crew_securely(router, project_id, inputs):
    manager = AIOpsCrewManager(router=router, project_id=project_id, inputs=inputs)
    # The actual implementation of run_ai_dev_ops_crew_securely would go here
    return manager.run()
