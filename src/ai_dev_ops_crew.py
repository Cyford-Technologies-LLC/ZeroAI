# src/ai_dev_ops_crew.py

import os  # Unused
import sys  # Used implicitly by `traceback` and potentially other modules, but not directly. Keep for now.
import uuid  # Unused
import time  # Unused
import logging  # Used
import importlib  # Used
import traceback  # Used
from pathlib import Path  # Used
from typing import Dict, Any, Optional, List  # Used
from rich.console import Console  # Used
from rich.table import Table  # Used
from src.utils.custom_logger_callback import CustomLogger  # Unused in this file. Remove.
from src.crews.internal.team_manager.agents import ErrorLogger
from src.crews.internal.tools.git_tool import GitTool, FileTool

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

    # Try to import the ErrorLogger first
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

    crew_dirs = [d for d in internal_crews_dir.iterdir() if d.is_dir() and not d.name.startswith("__") and d.name != "tools"]

    console.print(f"🔍 [bold blue]Checking internal crews availability[/bold blue]")
    console.print(f"Found {len(crew_dirs)} potential internal crews", style="blue")

    for crew_dir in crew_dirs:
        crew_name = crew_dir.name
        crew_status[crew_name] = {
            "status": "unknown",
            "error": None,
            "files_present": [],
            "directory": str(crew_dir),
            "agents": []  # Added to store agent creator function names
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

            # Import the agents module as well
            agents_import_path = f"src.crews.internal.{crew_name}.agents"
            agents_module = importlib.import_module(agents_import_path)

            crew_status[crew_name]["status"] = "available"
            crew_status[crew_name]["module"] = import_path

            get_crew_func = f"get_{crew_name}_crew"
            if hasattr(module, get_crew_func):
                crew_status[crew_name]["get_crew_function"] = get_crew_func

            # Find agent creator functions in the agents module
            for func_name in dir(agents_module):
                if func_name.startswith("create_") and func_name.endswith("_agent"):
                    crew_status[crew_name]["agents"].append(func_name)

            # Check if any agent creation functions were found
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
        status_style = "green" if info["status"] == "available" else "yellow" if info["status"] == "incomplete" else "red"
        console.print(f"[bold]{crew_name}[/bold]: [{status_style}]{info['status']}[/{status_style}]")
        if info["error"]:
            console.print(f"  Error: {info['error']}")

    return crew_status



class AIOpsCrewManager:
    # ... (initializer and helper methods remain the same) ...
    """
    Manager for the AI DevOps Crew.
    Orchestrates secure execution of internal development and maintenance tasks
    by delegating to specialized sub-crews.
    """

    def __init__(self, router, project_id, inputs):
        """
        Initialize the AIOps Crew Manager.

        Args:
            router: The DevOps router instance for LLM routing
            project_id: The ID of the project being worked on
            inputs: Dictionary of input parameters
        """
        self.router = router
        self.project_id = project_id
        self.inputs = inputs
        self.task_id = inputs.get("task_id", str(uuid.uuid4()))
        self.prompt = inputs.get("prompt", "")
        self.category = inputs.get("category", "general")
        self.repository = inputs.get("repository")
        self.branch = inputs.get("branch", "main")

        # Initialize tracking information
        self.model_used = "unknown"
        self.peer_used = "unknown"
        self.token_usage = {"total_tokens": 0}
        self.base_url = None

        # Preload all crew modules
        self.crews_status = inputs.get("crews_status", {})
        if not self.crews_status:
            console.print("Preloading internal crews status...", style="blue")
            self.crews_status = preload_internal_crews()

        # Load project configuration
        self.project_config = self._load_project_config()

        # Set up working directory from project configuration
        self.working_dir = self._setup_working_dir()

        # Initialize the tools
        self.tools = self._initialize_tools()

    def execute(self) -> Dict[str, Any]:
        """Execute the task specified in the prompt using the appropriate crew."""
        try:
            start_time = time.time()
            log_output_path = self.working_dir / f"crew_log_{self.task_id}.json"
            custom_logger = CustomLogger(output_file=str(log_output_path))

            # ... (rest of the initial setup and model information extraction) ...
            try:
                llm = self.router.get_llm_for_role("general")
                if llm:
                    self.model_used = llm.model.replace("ollama/", "")
                    if hasattr(llm, 'base_url'):
                        self.base_url = llm.base_url
                        # Extract peer from base_url
                        if self.base_url:
                            try:
                                peer_ip = self.base_url.split('//')[1].split(':')
                                self.peer_used = peer_ip
                            except:
                                self.peer_used = "unknown"
            except Exception as e:
                console.print(f"⚠️ Could not extract model information: {e}", style="yellow")

            # Check if Team Manager is available
            if "team_manager" not in self.crews_status or self.crews_status["team_manager"]["status"] != "available":
                error_msg = "Team Manager crew is not available or has errors."
                console.print(f"❌ {error_msg}", style="red")
                if "team_manager" in self.crews_status and "error" in self.crews_status["team_manager"]:
                    error_msg += f" Error: {self.crews_status['team_manager']['error']}"
                return {
                    "success": False,
                    "error": error_msg,
                    "model_used": self.model_used,
                    "peer_used": self.peer_used,
                    "crews_status": self.crews_status
                }

            # Import the Team Manager crew
            try:
                console.print("🔄 Importing Team Manager crew...", style="blue")
                from src.crews.internal.team_manager.crew import create_team_manager_crew

                # Prepare task inputs
                task_inputs = {
                    "project_id": self.project_id,
                    "prompt": self.prompt,
                    "category": self.category,
                    "repository": self.repository,
                    "branch": self.branch,
                    "task_id": self.task_id,
                    "crews_status": self.crews_status,
                    "working_dir": self.working_dir
                }

                # Create the team manager crew
                crew = create_team_manager_crew(
                    router=self.router,
                    tools=self.tools,
                    project_config=self.project_config,
                    inputs=task_inputs,
                )

                # Assign the custom logger callback
                if custom_logger:
                    crew.callbacks = [custom_logger.log_step_callback]

                if crew is None:
                    error_msg = "❌ Error: Crew not created because no worker agents were found."
                    console.print(error_msg, style="red")
                    return {
                        "success": False,
                        "error": error_msg,
                        "model_used": self.model_used,
                        "peer_used": self.peer_used,
                        "crews_status": self.crews_status,

                    }

                # Execute the crew
                console.print(f"🚀 Executing Team Manager crew for task: {self.prompt}", style="blue")
                result = crew.kickoff()

                # Save the log after kickoff
                custom_logger.save_log()

                if self.project_config.get("crewai_settings", {}).get("verbose", 1):
                    console.print(f"\nFinal Result:\n{result}")

            except ImportError as e:
                console.print(f"❌ Could not import Team Manager crew: {e}", style="red")
                console.print("Traceback:", style="red")
                console.print(traceback.format_exc())

                try:
                    from src.crews.internal.team_manager.agents import ErrorLogger
                    error_logger = ErrorLogger()
                    error_logger.log_error(
                        f"Failed to import Team Manager crew: {str(e)}",
                        {
                            "project_id": self.project_id,
                            "traceback": traceback.format_exc(),
                            "sys_path": str(sys.path)
                        }
                    )
                except ImportError:
                    console.print("⚠️ Could not import ErrorLogger", style="yellow")

                raise

            # Process the result
            if result:
                # Extract token usage if available
                if hasattr(crew, "usage_metrics"):
                    self.token_usage = crew.usage_metrics

                return {
                    "success": True,
                    "message": "Task completed successfully",
                    "result": result,
                    "model_used": self.model_used,
                    "peer_used": self.peer_used,
                    "token_usage": self.token_usage,
                    "execution_time": time.time() - start_time,
                    "crews_status": self.crews_status
                }
            else:
                return {
                    "success": False,
                    "error": "Crew execution did not return a result",
                    "model_used": self.model_used,
                    "peer_used": self.peer_used,
                    "crews_status": self.crews_status
                }

        except Exception as e:
            console.print(f"❌ Error executing task: {e}", style="red")
            console.print("Traceback:", style="red")
            console.print(traceback.format_exc())

            try:
                from src.crews.internal.team_manager.agents import ErrorLogger
                error_logger = ErrorLogger()
                error_logger.log_error(
                    f"Error executing task: {str(e)}",
                    {
                        "project_id": self.project_id,
                        "prompt": self.prompt,
                        "category": self.category,
                        "traceback": traceback.format_exc()
                    }
                )
            except ImportError:
                console.print("⚠️ Could not import ErrorLogger", style="yellow")

            return {
                "success": False,
                "error": str(e),
                "model_used": self.model_used,
                "peer_used": self.peer_used,
                "crews_status": self.crews_status
            }

    def _load_project_config(self) -> Dict[str, Any]:
        """Load the project configuration from YAML file."""
        try:
            # Import here to avoid circular imports
            from utils.yaml_utils import load_yaml_config

            config_path = Path(f"knowledge/internal_crew/{self.project_id}/project_config.yaml")

            if not config_path.exists():
                console.print(f"⚠️ No config found for project '{self.project_id}', using default", style="yellow")
                return {
                    "project": {"name": self.project_id},
                    "crewai_settings": {"working_directory": f"/tmp/internal_crew/{self.project_id}/"}
                }

            config = load_yaml_config(config_path)
            console.print(f"✅ Loaded project config for '{self.project_id}'", style="green")
            return config
        except Exception as e:
            console.print(f"❌ Error loading project config: {e}", style="red")
            # Return a minimal default config
            return {
                "project": {"name": self.project_id},
                "crewai_settings": {"working_directory": f"/tmp/internal_crew/{self.project_id}/"}
            }

    def _setup_working_dir(self) -> Path:
        """Set up the working directory for the task based on project configuration."""
        try:
            # Get the working directory from the project config, or use a default
            working_dir_str = self.project_config.get("crewai_settings", {}).get("working_directory",
                                                    f"/tmp/internal_crew/{self.project_id}/")

            # Replace any task_id placeholders in the path
            working_dir_str = working_dir_str.replace("{task_id}", self.task_id)

            # Create a Path object
            working_dir = Path(working_dir_str)

            # Create the directory
            working_dir.mkdir(parents=True, exist_ok=True)

            console.print(f"✅ Set up working directory: {working_dir}", style="green")
            return working_dir
        except Exception as e:
            console.print(f"❌ Failed to set up working directory: {e}", style="red")

            # Log this error to the errors directory
            try:
                from src.crews.internal.team_manager.agents import ErrorLogger
                error_logger = ErrorLogger()
                error_logger.log_error(
                    f"Failed to set up working directory: {str(e)}",
                    {"project_id": self.project_id, "task_id": self.task_id}
                )
            except ImportError:
                console.print("⚠️ Could not import ErrorLogger", style="yellow")

            # Return a temporary directory as fallback
            import tempfile
            return Path(tempfile.mkdtemp(prefix=f"aiops_{self.project_id}_"))

    # Assuming ErrorLogger is imported at the top of the file containing AIOpsCrewManager


    def _initialize_tools(self) -> List[Any]:
        """Initialize and return the tools needed for the crews."""
        try:
            # Initialize the tools with the working directory
            git_tool = GitTool(working_dir=str(self.working_dir))
            file_tool = FileTool(working_dir=str(self.working_dir))
            tools = [git_tool, file_tool]

            console.print("✅ Initialized tools for crews", style="green")
            return tools

        except ImportError as e:
            error_msg = f"⚠️ Could not import required tools. Crews will run without tools: {e}"
            console.print(error_msg, style="yellow")

            # Log the error using the logger from the higher scope
            try:
                error_logger = ErrorLogger()
                error_logger.log_error(
                    error_msg,
                    {"project_id": self.project_id, "tools_attempted": "GitTool, FileTool"}
                )
            except Exception:
                console.print("⚠️ Could not import ErrorLogger", style="yellow")

            return []

        except Exception as e:
            error_msg = f"❌ Error initializing tools: {e}"
            console.print(error_msg, style="red")

            # Log the error using the logger from the higher scope
            try:
                error_logger = ErrorLogger()
                error_logger.log_error(
                    error_msg,
                    {"project_id": self.project_id, "tools_attempted": "GitTool, FileTool",
                     "traceback": traceback.format_exc()}
                )
            except Exception:
                console.print("⚠️ Could not import ErrorLogger", style="yellow")

            return []


if __name__ == "__main__":
    # This module should not be imported, not run directly
    print("This module should be imported, not run directly.")
    sys.exit(1)
