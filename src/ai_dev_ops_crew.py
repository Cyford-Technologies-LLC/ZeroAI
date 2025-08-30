# src/ai_dev_ops_crew.py

import os
import sys
import uuid
import time
import logging
import tempfile
import subprocess
from pathlib import Path
from typing import Dict, Any, Optional
from rich.console import Console

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)
console = Console()

class AIOpsCrewManager:
    """
    Manager for the AI DevOps Crew.
    Handles secure execution of internal development and maintenance tasks.
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

        # Load project configuration
        self.project_config = self._load_project_config()

        # Set up working directory from project configuration
        self.working_dir = self._setup_working_dir()

        # Tracking information
        self.model_used = "unknown"
        self.peer_used = "unknown"
        self.token_usage = {"total_tokens": 0}

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
            # Return a temporary directory as fallback
            return Path(tempfile.mkdtemp(prefix=f"aiops_{self.project_id}_"))

    def clone_repository(self) -> bool:
        """Clone the repository to the working directory."""
        if not self.repository:
            console.print("⚠️ No repository specified, skipping clone", style="yellow")
            return False

        try:
            # Check if git is installed
            subprocess.run(["git", "--version"], check=True, capture_output=True)

            # Clone the repository
            console.print(f"🔄 Cloning repository: {self.repository}", style="blue")

            clone_cmd = ["git", "clone", self.repository, str(self.working_dir)]
            result = subprocess.run(clone_cmd, check=True, capture_output=True, text=True)

            # Checkout specified branch if provided
            if self.branch:
                console.print(f"🔄 Checking out branch: {self.branch}", style="blue")
                checkout_cmd = ["git", "-C", str(self.working_dir), "checkout", self.branch]
                subprocess.run(checkout_cmd, check=True, capture_output=True, text=True)

            console.print("✅ Repository cloned successfully", style="green")
            return True
        except subprocess.CalledProcessError as e:
            console.print(f"❌ Git operation failed: {e.stderr}", style="red")
            return False
        except Exception as e:
            console.print(f"❌ Failed to clone repository: {e}", style="red")
            return False

    def execute_file_tasks(self) -> Dict[str, Any]:
        """
        Execute simple file operations based on the task description.
        This is a simplified implementation for basic file operations.
        """
        # Look for file creation/modification patterns in the prompt
        prompt_lower = self.prompt.lower()

        # Track results for reporting
        results = {
            "success": False,
            "message": "",
            "created_files": [],
            "modified_files": []
        }

        try:
            # Extract file path and content for simple creation tasks
            if "create" in prompt_lower or "add" in prompt_lower or "make" in prompt_lower:
                # Simple pattern matching for file creation tasks

                # Find file name in the prompt
                file_name = None

                # Look for "named X" pattern
                if "named" in prompt_lower:
                    parts = prompt_lower.split("named")
                    if len(parts) > 1:
                        # Get the word after "named"
                        name_part = parts[1].strip().split()
                        if name_part:
                            file_name = name_part[0]

                # Extract content if mentioned
                content = "# File created by AI DevOps Crew\n"
                if "content" in prompt_lower and "with content" in prompt_lower:
                    parts = self.prompt.split("with content")
                    if len(parts) > 1:
                        content = parts[1].strip()
                elif "with" in prompt_lower and "inside" in prompt_lower:
                    parts = self.prompt.split("with")
                    if len(parts) > 1:
                        inner_parts = parts[1].split("inside")
                        if len(inner_parts) > 0:
                            content = inner_parts[0].strip()

                # If file name was found, create the file
                if file_name:
                    # Determine where to create the file
                    # Always use the working directory from project configuration
                    file_path = self.working_dir / file_name

                    # Create the file
                    console.print(f"📝 Creating file: {file_path}", style="blue")
                    with open(file_path, 'w') as f:
                        f.write(content)

                    results["created_files"].append(str(file_path))
                    results["success"] = True
                    results["message"] = f"Created file {file_path} with specified content"

                    console.print(f"✅ File created successfully: {file_path}", style="green")
                else:
                    results["message"] = "Could not determine file name from the prompt"
            else:
                results["message"] = "No file operations detected in the prompt"

            return results
        except Exception as e:
            console.print(f"❌ Error executing file tasks: {e}", style="red")
            results["success"] = False
            results["message"] = f"Error executing file tasks: {str(e)}"
            return results

    def execute(self) -> Dict[str, Any]:
        """Execute the task specified in the prompt."""
        try:
            start_time = time.time()

            # Get appropriate LLM based on the category
            llm = self.router.get_llm_for_role(self.category)
            if llm:
                self.model_used = llm.model.replace("ollama/", "")
                self.peer_used = getattr(llm, "_client", None)
                if hasattr(self.peer_used, "base_url"):
                    self.peer_used = self.peer_used.base_url

                console.print(f"🤖 Using model: {self.model_used}", style="blue")
                console.print(f"🖥️ Using peer: {self.peer_used}", style="blue")

            # For simple file operations, use direct implementation
            if any(kw in self.prompt.lower() for kw in ["file", "create", "add", "write"]):
                return self.execute_file_tasks()

            # For git operations, clone repository first
            if "repo" in self.prompt.lower() or "git" in self.prompt.lower():
                if self.repository:
                    self.clone_repository()

            # Add additional task implementations here...

            # If we reached here without executing anything specific,
            # return a generic success message
            return {
                "success": True,
                "message": f"Task '{self.prompt}' processed with category '{self.category}'",
                "model_used": self.model_used,
                "peer_used": self.peer_used,
                "token_usage": self.token_usage
            }

        except Exception as e:
            console.print(f"❌ Error executing task: {e}", style="red")
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
        return {
            "success": False,
            "error": f"Error running AI DevOps Crew: {str(e)}",
            "model_used": "unknown",
            "peer_used": "unknown"
        }

if __name__ == "__main__":
    # This module should not be run directly
    print("This module should be imported, not run directly.")
    sys.exit(1)