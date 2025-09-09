import os
import sys
import signal
import uuid
import time
import importlib
import traceback
import yaml
from pathlib import Path
from typing import Dict, Any, Optional, List
from rich.console import Console
from rich.table import Table
from pathvalidate import sanitize_filepath
from src.utils.env_loader import load_secure_env, get_secure_token

# Load secure environment variables at startup
load_secure_env()


from crewai import Crew
from src.distributed_router import DistributedRouter
from src.config import config  # Keep only one import
from src.utils.config_loader import load_internal_config
# from src.utils.custom_logger import CustomLogger
from src.utils.yaml_utils import load_yaml_config

# Import specific agents and tools
from src.crews.internal.team_manager.crew import create_team_manager_crew
from src.crews.internal.team_manager.agents import ErrorLogger, create_team_manager_agent, load_all_coworkers
# from src.crews.internal.team_manager.tasks import create_planning_task
from src.crews.internal.tools.docker_tool import DockerTool
from src.crews.internal.tools.git_tool import GitTool, FileTool, create_git_tool
from tool_factory import dynamic_github_tool
from src.utils.custom_logger_callback import CustomLogger

# Configure console
console = Console()

# Global flag for graceful shutdown
shutdown_requested = False

def signal_handler(signum, frame):
    """Handle interruption signals gracefully"""
    global shutdown_requested
    shutdown_requested = True
    console.print("\n\n🛑 [bold yellow]Crew execution interrupted. Cleaning up...[/bold yellow]")

# Register signal handler
signal.signal(signal.SIGINT, signal_handler)


# The preload_internal_crews function remains unchanged
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
        
        # Load internal configuration
        self.internal_config = load_internal_config()
        self.persistent_enabled = self.internal_config.get("persistent_crews", {}).get("enabled", True)

        self.crews_status = inputs.get("crews_status", {})
        if not self.crews_status:
            console.print("Preloading internal crews status...", style="blue")
            self.crews_status = preload_internal_crews()

        # Load project-specific config
        self.project_config = self._load_project_config()
        
        # Debug output
        console.print(f"DEBUG: project_config type: {type(self.project_config)}", style="magenta")
        console.print(f"DEBUG: project_config value: {self.project_config}", style="magenta")
        
        # Ensure project_config is not None
        if self.project_config is None:
            console.print("WARNING: project_config is None, creating default", style="yellow")
            self.project_config = {
                "project": {"name": self.project_id},
                "repository": {},
                "crewai_settings": {"working_directory": f"/tmp/internal_crew/{self.project_id}/"}
            }

        # Ensure 'repository' key exists and is not None before accessing it
        if 'repository' not in self.project_config or self.project_config['repository'] is None:
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
                    "repository": {},
                    "crewai_settings": {"working_directory": f"/tmp/internal_crew/{self.project_id}/"}
                }
            else:
                from src.utils.yaml_utils import load_yaml_config
                project_config = load_yaml_config(config_path)
                console.print(f"✅ Loaded project config for '{self.project_id}'", style="green")
                
                # Ensure repository key exists
                if not project_config:
                    project_config = {}
                if 'repository' not in project_config:
                    project_config['repository'] = {}

            return project_config
        except Exception as e:
            console.print(f"❌ Error loading project config: {e}", style="red")
            return {
                "project": {"name": self.project_id},
                "repository": {},
                "crewai_settings": {"working_directory": f"/tmp/internal_crew/{self.project_id}/"}
            }

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
        """Initialize tools for the crew."""
        tools = []
        
        # Add basic tools
        try:
            tools.extend([DockerTool(), FileTool()])
        except Exception as e:
            console.print(f"⚠️ Warning: Could not initialize basic tools: {e}", style="yellow")
        
        # Add GitHub tool
        try:
            tools.append(dynamic_github_tool)
        except Exception as e:
            console.print(f"⚠️ Warning: Could not initialize GitHub tool: {e}", style="yellow")
        
        return tools
    
    def _get_optimal_llm(self):
        """Get the best available LLM - cloud AI if configured, otherwise local."""
        try:
            # Check if cloud AI is configured and available
            if config.cloud.provider != "local":
                from src.providers.cloud_providers import CloudProviderManager
                
                if config.cloud.provider == "anthropic" and os.getenv('ANTHROPIC_API_KEY'):
                    console.print("🌩️ Using Claude (Anthropic) for enhanced capabilities", style="cyan")
                    return CloudProviderManager.create_anthropic_llm(model='claude-3-5-sonnet-20241022')
                elif config.cloud.provider == "openai" and os.getenv('OPENAI_API_KEY'):
                    console.print("🌩️ Using GPT-4 (OpenAI) for enhanced capabilities", style="cyan")
                    return CloudProviderManager.create_openai_llm(model='gpt-4')
                elif config.cloud.provider == "azure" and os.getenv('AZURE_OPENAI_API_KEY'):
                    console.print("🌩️ Using Azure OpenAI for enhanced capabilities", style="cyan")
                    return CloudProviderManager.create_azure_llm()
                elif config.cloud.provider == "google" and os.getenv('GOOGLE_API_KEY'):
                    console.print("🌩️ Using Google Gemini for enhanced capabilities", style="cyan")
                    return CloudProviderManager.create_google_llm()
                else:
                    console.print(f"⚠️ Cloud provider '{config.cloud.provider}' configured but API key not found, falling back to local", style="yellow")
            
            # Fallback to local/distributed routing
            return self.router.get_llm_for_role("general")
            
        except ImportError:
            console.print("⚠️ Cloud providers not available, using local LLM", style="yellow")
            return self.router.get_llm_for_role("general")
        except Exception as e:
            console.print(f"⚠️ Error getting optimal LLM: {e}, using local", style="yellow")
            return self.router.get_llm_for_role("general")

    def execute(self) -> Dict[str, Any]:
        """Execute the task specified in the prompt using the appropriate crew."""
        try:
            # Check for shutdown request at start
            if shutdown_requested:
                console.print("Shutdown requested. Aborting crew execution.", style="yellow")
                return {"success": False, "error": "Crew execution aborted by user"}
                
            # Sanitize project_id to prevent path traversal vulnerabilities
            sanitized_project_id = sanitize_filepath(self.project_id)

            start_time = time.time()
            log_output_path = self.working_dir / f"crew_log_{self.task_id}.json"
            custom_logger = CustomLogger(output_file=str(log_output_path))

            self.model_used, self.peer_used = "unknown", "unknown"
            try:
                # Check for cloud AI availability first
                llm = self._get_optimal_llm()
                if llm:
                    self.model_used = getattr(llm, 'model', 'unknown')
                    if 'anthropic' in self.model_used or 'claude' in self.model_used:
                        self.peer_used = "claude-api"
                    elif 'openai' in self.model_used or 'gpt' in self.model_used:
                        self.peer_used = "openai-api"
                    elif hasattr(llm, 'base_url') and llm.base_url:
                        self.base_url = llm.base_url
                        try:
                            # Safely split and extract peer_ip
                            parts = self.base_url.split('//')
                            if len(parts) > 1:
                                peer_parts = parts[1].split(':')
                                if peer_parts:
                                    self.peer_used = peer_parts[0]
                        except Exception as e:
                            console.print(f"⚠️ Error parsing peer IP: {e}", style="yellow")
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

                # --- Handle repo URL override and token retrieval ---
                final_repo_url = self.project_config.get("repository", {}).get("url")
                if self.repository:  # self.repository is set from a CLI argument
                    final_repo_url = self.repository
                    console.print(f"✅ Overriding project repository with CLI value: {final_repo_url}", style="green")

                # Get token key from project_config.yaml repository.REPO_TOKEN_KEY
                repo_token_key = None
                repo_token = None
                
                # Get token key from project config
                if self.project_config and 'repository' in self.project_config:
                    repo_config = self.project_config['repository']
                    if isinstance(repo_config, dict):
                        repo_token_key = repo_config.get('REPO_TOKEN_KEY', '')
                
                # Default token key based on project name if not found in config
                if not repo_token_key:
                    if "cyford" in self.project_id.lower() or "zeroai" in self.project_id.lower():
                        repo_token_key = "GH_TOKEN_CYFORD"
                    elif "testcorp" in self.project_id.lower():
                        repo_token_key = "GITHUB_TOKEN_TESTCORP"
                    else:
                        repo_token_key = "GH_TOKEN_CYFORD"  # Default to Cyford token
                
                # Remove curly braces if present: {GH_TOKEN_CYFORD} -> GH_TOKEN_CYFORD
                if repo_token_key.startswith("{") and repo_token_key.endswith("}"):
                    repo_token_key = repo_token_key[1:-1]
                
                # Get token using secure loader
                if repo_token_key:
                    repo_token = get_secure_token(repo_token_key)
                    if not repo_token and hasattr(config, 'github_tokens') and config.github_tokens:
                        repo_token = config.github_tokens.get(repo_token_key.lower().replace('gh_token_', '').replace('github_token_', ''))
                        if hasattr(repo_token, 'get_secret_value'):
                            repo_token = repo_token.get_secret_value()

                # --- Enhanced debugging ---
                console.print(f"DEBUG: Token key from Company_Details: {repo_token_key}", style="magenta")
                console.print(f"DEBUG: Using final_repo_url: {final_repo_url}", style="magenta")
                console.print(f"DEBUG: Retrieved repo_token: {'***' if repo_token else 'None'}", style="magenta")
                console.print(f"DEBUG: Environment check - {repo_token_key}: {'SET' if os.getenv(repo_token_key) else 'NOT SET'}", style="magenta")
                console.print(f"DEBUG: Available env vars: {[k for k in os.environ.keys() if 'TOKEN' in k or 'GH_' in k]}", style="magenta")
                # --- End repo logic ---

                task_inputs = {
                    "project_id": sanitized_project_id,
                    "prompt": self.prompt,
                    "category": self.category,
                    "repository": final_repo_url,  # Pass the final URL here
                    "branch": self.branch,
                    "task_id": self.task_id,
                    "crews_status": self.crews_status,
                    "working_dir": self.working_dir,
                    "repo_token": repo_token,  # Pass the token here
                    "repo_token_key": repo_token_key,  # Pass the token key here
                    "project_config": self.project_config,

                    # Pass project config to agents
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
                
                # Check for shutdown request before crew kickoff
                if shutdown_requested:
                    console.print("Shutdown requested before crew kickoff. Aborting.", style="yellow")
                    return {"success": False, "error": "Crew kickoff aborted by user"}
                    
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
                token_usage = getattr(crew, "usage_metrics", None)
                return {
                    "success": True,
                    "message": "Task completed successfully",
                    "result": result,
                    "model_used": self.model_used,
                    "peer_used": self.peer_used,
                    "token_usage": token_usage,
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

        except KeyboardInterrupt:
            console.print("\n\n🛑 [bold yellow]Crew execution interrupted by user[/bold yellow]")
            return {"success": False, "error": "Crew execution interrupted by user"}
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
