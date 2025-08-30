# src/ai_dev_ops_crew.py

import os
import sys
import uuid
import time
import logging
import traceback
from pathlib import Path
from typing import Dict, Any, Optional, List
from rich.console import Console

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)
console = Console()

class AIOpsCrewManager:
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

        # Load project configuration
        self.project_config = self._load_project_config()

        # Set up working directory from project configuration
        self.working_dir = self._setup_working_dir()

        # Initialize the tools
        self.tools = self._initialize_tools()

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
            from src.crews.internal.team_manager.agent import ErrorLogger
            error_logger = ErrorLogger()
            error_logger.log_error(
                f"Failed to set up working directory: {str(e)}",
                {"project_id": self.project_id, "task_id": self.task_id}
            )

            # Return a temporary directory as fallback
            import tempfile
            return Path(tempfile.mkdtemp(prefix=f"aiops_{self.project_id}_"))

    def _initialize_tools(self) -> List[Any]:
        """Initialize and return the tools needed for the crews."""
        tools = []

        try:
            # Import the tools
            from tools.git_tool import GitTool, FileTool

            # Initialize the tools with the working directory
            git_tool = GitTool(working_dir=str(self.working_dir))
            file_tool = FileTool(working_dir=str(self.working_dir))

            tools = [git_tool, file_tool]
            console.print("✅ Initialized tools for crews", style="green")
        except ImportError as e:
            console.print(f"⚠️ Could not import tools, crews will run without tools: {e}", style="yellow")
        except Exception as e:
            console.print(f"❌ Error initializing tools: {e}", style="red")

            # Log this error to the errors directory
            from src.crews.internal.team_manager.agent import ErrorLogger
            error_logger = ErrorLogger()
            error_logger.log_error(
                f"Error initializing tools: {str(e)}",
                {"project_id": self.project_id, "tools_attempted": "GitTool, FileTool"}
            )

        return tools

    def execute(self) -> Dict[str, Any]:
        """Execute the task specified in the prompt using the appropriate crew."""
        try:
            start_time = time.time()

            # Extract and record LLM model information if we can get it
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

            # Import the Team Manager crew
            try:
                console.print("🔄 Importing Team Manager crew...", style="blue")
                from src.crews.internal.team_manager.crew import get_team_manager_crew

                # Prepare task inputs
                task_inputs = {
                    "project_id": self.project_id,
                    "prompt": self.prompt,
                    "category": self.category,
                    "repository": self.repository,
                    "branch": self.branch,
                    "task_id": self.task_id
                }

                # Create and execute the team manager crew
                crew = get_team_manager_crew(
                    router=self.router,
                    tools=self.tools,
                    project_config=self.project_config,
                    task_inputs=task_inputs
                )

                # Execute the crew
                console.print(f"🚀 Executing Team Manager crew for task: {self.prompt}", style="blue")
                result = crew.kickoff()

            except ImportError as e:
                console.print(f"❌ Could not import Team Manager crew: {e}", style="red")
                console.print("Traceback:", style="red")
                console.print(traceback.format_exc())

                # Log this error to the errors directory
                from src.crews.internal.team_manager.agent import ErrorLogger
                error_logger = ErrorLogger()
                error_logger.log_error(
                    f"Failed to import Team Manager crew: {str(e)}",
                    {
                        "project_id": self.project_id,
                        "traceback": traceback.format_exc(),
                        "sys_path": str(sys.path)
                    }
                )

                raise

            # Process the result
            if result:
                # Extract token usage if available
                if hasattr(result, "token_usage"):
                    self.token_usage = result.token_usage

                # Return the result with additional metadata
                return {
                    "success": True,
                    "message": "Task completed successfully",
                    "result": result,
                    "model_used": self.model_used,
                    "peer_used": self.peer_used,
                    "token_usage": self.token_usage,
                    "execution_time": time.time() - start_time
                }
            else:
                return {
                    "success": False,
                    "error": "Crew execution did not return a result",
                    "model_used": self.model_used,
                    "peer_used": self.peer_used
                }

        except Exception as e:
            console.print(f"❌ Error executing task: {e}", style="red")
            console.print("Traceback:", style="red")
            console.print(traceback.format_exc())

            # Log this error to the errors directory
            from src.crews.internal.team_manager.agent import ErrorLogger
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

            return {
                "success": False,
                "error": str(e),
                "model_used": self.model_used,
                "peer_used": self.peer_used
            }

def run_ai_dev_ops_crew_securely(router, project_id, inputs) -> Dict[str, Any]:
    """
    Securely run the AI DevOps Crew.

    Args:
        router: The DevOps router instance
        project_id: The ID of the project to work on
        inputs: Dictionary of input parameters

    Returns:
        Dictionary with task results
    """
    try:
        manager = AIOpsCrewManager(router, project_id, inputs)
        return manager.execute()
    except Exception as e:
        logger.error(f"Error running AI DevOps Crew: {e}")

        # Log this error to the errors directory
        try:
            from src.crews.internal.team_manager.agent import ErrorLogger
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
            "peer_used": "unknown"
        }

if __name__ == "__main__":
    # This module should not be imported, not run directly
    print("This module should be imported, not run directly.")
    sys.exit(1)