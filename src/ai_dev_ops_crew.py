import os
import sys
import uuid
import time
import importlib
import traceback
import yaml
from pathlib import Path
from typing import Dict, Any, Optional, List
from rich.console import Console
from rich.table import Table

from crewai import Crew
from distributed_router import DistributedRouter
from src.config import config  # Keep only one import
# from src.utils.custom_logger import CustomLogger
from src.utils.yaml_utils import load_yaml_config

# Import specific agents and tools
from src.crews.internal.team_manager.agents import ErrorLogger, create_team_manager_agent, load_all_coworkers
# from src.crews.internal.team_manager.tasks import create_planning_task
from src.crews.internal.tools.docker_tool import DockerTool
from src.crews.internal.tools.git_tool import GitTool, FileTool, create_git_tool
from tool_factory import dynamic_github_tool
from src.utils.custom_logger_callback import CustomLogger

# Configure console
console = Console()


# The preload_internal_crews function remains unchanged
def preload_internal_crews() -> Dict[str, Dict[str, Any]]:
    # ... (same as before) ...
    pass


class AIOpsCrewManager:
    """
    Manager for the AI DevOps Crew.
    Orchestrates secure execution of internal development and maintenance tasks
    by delegating to specialized sub-crews.
    """

    def __init__(self, router, project_id, inputs):
        """
        Initialize the AIOps Crew Manager.
        """
        self.router = router
        self.project_id = project_id
        self.inputs = inputs
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

        # Load project-specific config
        self.project_config = self._load_project_config()

        # Ensure 'repository' key exists before accessing it
        if self.repository and 'repository' not in self.project_config:
            self.project_config['repository'] = {}

        # Override repository URL if provided in inputs
        if self.repository:
            self.project_config['repository']['url'] = self.repository

        self.working_dir = self._setup_working_dir()
        self.tools = self._initialize_tools()

        # self.config is not strictly necessary here since `src.config.config` is a global object
        # and should be accessed directly by other modules.
        # This line is primarily useful for debugging or if you needed a local copy of the config.
        # For now, let's keep it to maintain consistency with previous logic.
        self.config = config

    def _load_project_config(self) -> Dict[str, Any]:
        """Load the project configuration from YAML file, with URL override."""
        try:
            config_path = Path(f"knowledge/internal_crew/{self.project_id}/project_config.yaml")

            if not config_path.exists():
                console.print(f"⚠️ No config found for project '{self.project_id}', using default", style="yellow")
                project_config = {
                    "project": {"name": self.project_id},
                    "crewai_settings": {"working_directory": f"/tmp/internal_crew/{self.project_id}/"}
                }
            else:
                from src.utils.yaml_utils import load_yaml_config
                project_config = load_yaml_config(config_path)
                console.print(f"✅ Loaded project config for '{self.project_id}'", style="green")

            return project_config
        except Exception as e:
            # ... (original error handling) ...
            pass  # Removed for brevity

    # src/ai_dev_ops_crew.py

    def _setup_working_dir(self) -> Path:
        """Set up the working directory for the task based on project configuration."""
        try:
            working_dir_str = self.project_config.get("crewai_settings", {}).get("working_directory",
                                                                                 f"/tmp/internal_crew/{self.project_id}/")

            working_dir_str = working_dir_str.replace("{task_id}", self.task_id)
            working_dir = Path(working_dir_str)
            working_dir.mkdir(parents=True, exist_ok=True)
            console.print(f"✅ Set up working directory: {working_dir}", style="green")
            return working_dir
        except Exception as e:
            console.print(f"❌ Failed to set up working directory: {e}", style="red")

            try:
                from src.crews.internal.team_manager.agents import ErrorLogger
                error_logger = ErrorLogger()
                error_logger.log_error(
                    f"Failed to set up working directory: {str(e)}",
                    {"project_id": self.project_id, "task_id": self.task_id}
                )
            except ImportError:
                console.print("⚠️ Could not import ErrorLogger", style="yellow")

            import tempfile
            # This line ensures a valid Path object is returned
            return Path(tempfile.mkdtemp(prefix=f"aiops_{self.project_id}_"))

    def _initialize_tools(self) -> List[Any]:
        # ... (remains unchanged) ...
        pass



    def execute(self) -> Dict[str, Any]:
        """Execute the task specified in the prompt using the appropriate crew."""
        try:
            start_time = time.time()
            log_output_path = self.working_dir / f"crew_log_{self.task_id}.json"
            custom_logger = CustomLogger(output_file=str(log_output_path))

            try:
                llm = self.router.get_llm_for_role("general")
                if llm:
                    self.model_used = llm.model.replace("ollama/", "")
                    if hasattr(llm, 'base_url'):
                        self.base_url = llm.base_url
                        # Extract peer from base_url
                        if self.base_url:
                            try:
                                peer_ip = self.base_url.split('//')[1].split(':')[0]
                                self.peer_used = peer_ip
                            except:
                                self.peer_used = "unknown"
            except Exception as e:
                console.print(f"⚠️ Could not extract model information: {e}", style="yellow")

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

            try:
                console.print("🔄 Importing Team Manager crew...", style="blue")
                from src.crews.internal.team_manager.crew import create_team_manager_crew

                task_inputs = {
                    "project_id": self.project_id,
                    "prompt": self.prompt,
                    "category": self.category,
                    "repository": self.repository,
                    "branch": self.branch,
                    "task_id": self.task_id,
                    "crews_status": self.crews_status,
                    "working_dir": self.working_dir,
                    "repo_token_key": self.project_config.get("repository", {}).get("REPO_TOKEN_KEY")
                }

                crew = create_team_manager_crew(
                    router=self.router,
                    tools=self.tools,
                    project_config=self.project_config,
                    inputs=task_inputs,
                    custom_logger=custom_logger
                )

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

                console.print(f"🚀 Executing Team Manager crew for task: {self.prompt}", style="blue")
                result = crew.kickoff()

                custom_logger.save_log()

                if self.project_config.get("crewai_settings", {}).get("verbose", 1):
                    console.print(f"\nFinal Result:\n{result}")

            except ImportError as e:
                console.print(f"❌ Could not import Team Manager crew: {e}", style="red")
                console.print("Traceback:", style="red")
                console.print(traceback.format_exc())

                try:
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

            if result:
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
            raise e


def run_ai_dev_ops_crew_securely(router, project_id, inputs) -> Dict[str, Any]:
    """
    Securely run the AI DevOps Crew.
    """
    try:
        # Preload all crew modules at startup
        crews_status = preload_internal_crews()

        # Add crews status to inputs
        inputs["crews_status"] = crews_status

        console.print(f"--- DEBUG: About to initialize AIOpsCrewManager ---", style="bold yellow")
        console.print(f"  router: {router}", style="dim")
        console.print(f"  project_id: {project_id}", style="dim")
        console.print(f"  inputs: {inputs}", style="dim")
        console.print(f"  config.model.name: {config.model.name}", style="dim")
        console.print(f"  config.model.temperature: {config.model.temperature}", style="dim")
        console.print(f"  config.model.base_url: {config.model.base_url}", style="dim")
        console.print(f"  config.agents.max_concurrent: {config.agents.max_concurrent}", style="dim")
        console.print(f"--- END DEBUG ---", style="bold yellow")

        # Initialize and run the manager
        manager = AIOpsCrewManager(router, project_id, inputs)
        return manager.execute()
    except Exception as e:
        # ... (error handling logic, likely already present) ...
        # Import ErrorLogger locally to prevent circular dependencies
        try:
            from src.crews.internal.team_manager.agents import ErrorLogger
            error_logger = ErrorLogger()
            error_logger.log_error(
                f"Error running AI DevOps Crew: {str(e)}",
                {
                    "project_id": project_id,
                    "inputs": str(inputs),
                    "traceback": traceback.format_exc()
                }
            )
        except ImportError:
            # If we can't import the error logger, just log to the console
            console.print(f"❌ Error running AI DevOps Crew and couldn't log to error directory: {e}", style="red")

        return {
            "success": False,
            "error": f"Error running AI DevOps Crew: {str(e)}",
            "model_used": "unknown",
            "peer_used": "unknown",
            "crews_status": preload_internal_crews()  # Include crews status in the error response
        }





if __name__ == "__main__":
    # This module should not be imported, not run directly
    print("This module should be imported, not run directly.")
    sys.exit(1)
