# src/ai_dev_ops_crew.py

import os
import sys
import uuid
import time
import logging
import tempfile
from pathlib import Path
from typing import Dict, Any, Optional, List
from rich.console import Console
from crewai import Crew, Process, Agent, Task
from src.utils.memory import Memory

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
            # Return a temporary directory as fallback
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

        return tools

    def _create_orchestrator_agent(self) -> Agent:
        """Create the DevOps Orchestrator Agent that delegates tasks."""
        try:
            # Get LLM for the orchestrator role
            llm = self.router.get_llm_for_role("devops_orchestrator")

            # Track model information
            if llm:
                self.model_used = llm.model.replace("ollama/", "")
                self.peer_used = getattr(llm, "_client", None)
                if hasattr(self.peer_used, "base_url"):
                    self.peer_used = self.peer_used.base_url
                if hasattr(llm, 'base_url'):
                    self.base_url = llm.base_url
                    # Extract peer from base_url
                    if self.base_url:
                        try:
                            peer_ip = self.base_url.split('//')[1].split(':')[0]
                            self.peer_used = peer_ip
                        except:
                            self.peer_used = "unknown"

            # Create a NEW dedicated memory instance for the orchestrator
            # CRITICAL: This is a separate memory instance just for the orchestrator
            orchestrator_memory = Memory(max_items=2000)

            console.print(f"👩‍💼 Creating orchestrator agent with dedicated memory...", style="blue")

            # Create the orchestrator agent WITH EXPLICIT INSTRUCTIONS about available actions
            orchestrator = Agent(
                role="DevOps Orchestrator",
                name="Commander Nova",
                memory=orchestrator_memory,  # Using dedicated memory instance
                goal=f"Analyze the task and delegate to appropriate sub-crews for project {self.project_id}",
                backstory="""You are the lead DevOps engineer responsible for orchestrating
                AI-driven development tasks. You analyze tasks, break them down into subtasks,
                and delegate to specialized crews.

                IMPORTANT: You can ONLY use two specific actions:
                1. 'Delegate work to coworker' - Use this to assign tasks to specialized teams
                2. 'Ask question to coworker' - Use this to get information from team members

                DO NOT try to perform tasks directly. ALWAYS use delegation or questions.
                When you have a final result, DO NOT try to explain it yourself - delegate the explanation task.""",
                llm=llm,
                tools=[],  # Empty list of tools is correct for manager agent
                verbose=True,
                allow_delegation=True  # Ensure delegation is enabled for the orchestrator
            )

            return orchestrator
        except Exception as e:
            console.print(f"❌ Error creating orchestrator agent: {e}", style="red")
            raise

    def _get_crew_for_category(self, category: str) -> Optional[Crew]:
        """Get the appropriate crew for the specified category."""
        try:
            # Create new memory instances for each agent in crews
            # DO NOT use shared memory

            if category == "developer":
                # Import the developer crew
                from crews.internal.developer.crew import get_developer_crew
                # Pass a flag to indicate that new memory instances should be created for each agent
                return get_developer_crew(self.router, self.tools, self.project_config, use_new_memory=True)

            elif category == "documentation":
                # Import the documentation crew
                from crews.internal.documentation.crew import get_documentation_crew
                # Pass a flag to indicate that new memory instances should be created for each agent
                return get_documentation_crew(self.router, self.tools, self.project_config, use_new_memory=True)

            elif category == "repo_manager":
                # Import the repo manager crew
                from crews.internal.repo_management.crew import get_repo_management_crew
                # Pass a flag to indicate that new memory instances should be created for each agent
                return get_repo_management_crew(self.router, self.tools, self.project_config, use_new_memory=True)

            elif category == "research":
                # Import the research crew
                from crews.internal.research.crew import get_research_crew
                # Pass a flag to indicate that new memory instances should be created for each agent
                return get_research_crew(self.router, self.tools, self.project_config, use_new_memory=True)

            console.print(f"⚠️ No specific crew found for category '{category}', using fallback delegation", style="yellow")
            return None

        except ImportError as e:
            console.print(f"⚠️ Could not import crew for category '{category}': {e}", style="yellow")
            return None
        except Exception as e:
            console.print(f"❌ Error getting crew for category '{category}': {e}", style="red")
            return None

    def _create_hierarchical_crew(self) -> Crew:
        """Create the hierarchical crew with the orchestrator and sub-crews."""
        orchestrator = self._create_orchestrator_agent()

        # Create the task for the orchestrator
        orchestrator_task = Task(
            description=f"""
            Analyze the following task and coordinate with sub-crews to complete it:

            TASK: {self.prompt}

            PROJECT: {self.project_id}
            CATEGORY: {self.category}
            REPOSITORY: {self.repository or 'Not specified'}
            BRANCH: {self.branch}

            Working directory: {self.working_dir}

            1. Analyze what needs to be done
            2. Identify the appropriate sub-crew(s) for this task
            3. Coordinate the execution of the task
            4. Ensure all required files are created in the working directory
            5. Verify the task was completed successfully
            """,
            agent=orchestrator,
            expected_output="A detailed report of the task execution, including actions taken, sub-crews involved, and outcomes."
        )

        # Create the hierarchical crew
        # IMPORTANT: Don't include orchestrator in the agents list when it's set as manager_agent
        dev_ops_crew = Crew(
            agents=[],  # Empty list or list of other agents, but NOT including orchestrator
            tasks=[orchestrator_task],
            process=Process.hierarchical,
            verbose=True,
            manager_agent=orchestrator
        )

        return dev_ops_crew

    def execute(self) -> Dict[str, Any]:
        """Execute the task specified in the prompt using the appropriate crew."""
        try:
            start_time = time.time()

            # Try to get a specific crew for the category first
            crew = self._get_crew_for_category(self.category)

            # If no specific crew is found, use the hierarchical crew
            if crew is None:
                console.print(f"🔄 Creating hierarchical crew with orchestrator for task", style="blue")
                crew = self._create_hierarchical_crew()

            # Execute the crew
            console.print(f"🚀 Executing crew for task: {self.prompt}", style="blue")
            result = crew.kickoff()

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
    # This module should not be imported, not run directly
    print("This module should be imported, not run directly.")
    sys.exit(1)