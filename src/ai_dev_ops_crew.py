# src/ai_dev_ops_crew.py

import os
import json
import yaml
import time
import sys
import traceback
from pathlib import Path
from typing import Dict, Any
from dotenv import load_dotenv, find_dotenv

from crewai import Crew
from distributed_router import DistributedRouter
from config import config
from rich.console import Console

# Import specific agents and tools
from src.crews.internal.team_manager.agents import create_team_manager_agent, load_all_coworkers, ErrorLogger
from src.crews.internal.team_manager.tasks import create_planning_task
from src.crews.internal.tools.docker_tool import DockerTool
from src.crews.internal.tools.git_tool import GitTool, FileTool
from src.crews.internal.tool_factory import dynamic_github_tool  # The dynamic tool factory
from src.utils.custom_logger import CustomLogger

# Create the console instance
console = Console()


class AiDevOpsCrew:
    def __init__(self, project_id: str, prompt: str, category: str, repository: str, branch: str):
        self.project_id = project_id
        self.prompt = prompt
        self.category = category
        self.repository = repository
        self.branch = branch
        self.task_id = str(uuid.uuid4())[:8]
        self.working_dir = Path("knowledge/internal_crew") / self.project_id / "workspace"
        self.router = DistributedRouter()
        self.tools = []
        self.project_config = {}
        self.crews_status = self.router.discover_crews()
        self.model_used = "unknown"
        self.peer_used = "unknown"
        self.token_usage = {}

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

                # Read project config to get the token key
                project_config_path = Path("knowledge/internal_crew") / self.project_id / "project_config.yaml"
                if project_config_path.exists():
                    with open(project_config_path, 'r') as f:
                        self.project_config = yaml.safe_load(f)

                repo_token_key = self.project_config.get("repository", {}).get("REPO_TOKEN_KEY")

                # --- Conditionally add tools ---
                common_tools = [
                    DockerTool(),
                    GitTool(repo_path=self.working_dir),
                    FileTool(working_dir=self.working_dir),
                ]
                if repo_token_key:
                    common_tools.append(dynamic_github_tool)
                    console.print(f"✅ GitHub tool added with token key: {repo_token_key}", style="green")
                else:
                    console.print("⚠️ No GitHub token key found in project config. GitHub tool disabled.",
                                  style="yellow")

                # Prepare task inputs
                task_inputs = {
                    "project_id": self.project_id,
                    "prompt": self.prompt,
                    "category": self.category,
                    "repository": self.repository,
                    "branch": self.branch,
                    "task_id": self.task_id,
                    "crews_status": self.crews_status,
                    "working_dir": self.working_dir,
                    "repo_token_key": repo_token_key  # Pass the token key
                }

                # Create the team manager crew
                crew = create_team_manager_crew(
                    router=self.router,
                    tools=common_tools,
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
def run_ai_dev_ops_crew_securely(router, project_id, inputs) -> Dict[str, Any]:
    """
    Securely run the AI DevOps Crew.
    """
    try:
        # Preload all crew modules at startup
        crews_status = preload_internal_crews()

        # Add crews status to inputs
        inputs["crews_status"] = crews_status

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
