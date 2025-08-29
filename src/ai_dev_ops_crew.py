# src/ai_dev_ops_crew.py

import logging
from typing import Dict, Any, Optional, List
from crewai import Crew, Process, Agent, Task, CrewOutput
from rich.console import Console
from pydantic import BaseModel
import warnings

# Import existing router and tools
from distributed_router import DistributedRouter
from config import config

# Import internal sub-crews
from crews.internal.developer.crew import create_developer_crew
from crews.internal.documentation.crew import create_documentation_crew
from crews.internal.repo_manager.crew import create_repo_manager_crew
from crews.internal.research.crew import create_research_crew

# New Import for YAML loading
from utils.yaml_utils import load_yaml_config


# Define a minimal UsageMetrics class for CrewOutput compatibility
class UsageMetrics(BaseModel):
    total_tokens: Optional[int] = 0
    prompt_tokens: Optional[int] = 0
    completion_tokens: Optional[int] = 0
    successful_requests: Optional[int] = 0


console = Console()
logger = logging.getLogger(__name__)


class AIOpsCrewManager:
    """Manages the secure, internal DevOps crew creation and execution."""

    def __init__(self, distributed_router_instance: DistributedRouter, **kwargs):
        if not isinstance(distributed_router_instance, DistributedRouter):
            logger.error("FATAL: Router is not a DistributedRouter instance.")
            raise TypeError("Expected a DistributedRouter instance.")
        self.router = distributed_router_instance

        # Load YAML config based on project_id
        self.project_id = kwargs.get('project_id')
        if not self.project_id:
            raise ValueError("project_id is required to load the project config.")

        self.project_config = self._load_project_config()
        # Merge inputs with project config
        self.inputs = self._create_inputs_from_config(kwargs.get('inputs', {}))

        logger.info(f"AIOpsCrewManager initialized for project '{self.project_id}' with inputs: {self.inputs}")

    def _load_project_config(self) -> dict:
        """Loads the project configuration from the specified YAML file."""
        config_path = f"knowledge/internal_crew/{self.project_id}/project_config.yaml"
        return load_yaml_config(config_path)

    def _create_inputs_from_config(self, initial_inputs: dict) -> dict:
        """Merges YAML config into inputs for task execution."""
        full_inputs = {**initial_inputs, 'config': self.project_config}
        return full_inputs

    def execute_crew(self) -> CrewOutput:
        """Executes the hierarchical DevOps crew."""
        try:
            crew = self._create_dev_ops_crew()
            with warnings.catch_warnings():
                warnings.simplefilter("ignore", DeprecationWarning)
                result = crew.kickoff()
                return result
        except Exception as e:
            console.print(f"❌ Error during DevOps crew execution: {e}", style="red")
            return CrewOutput(tasks_output=[], raw=f"Error: {e}", token_usage=UsageMetrics())

    def _create_dev_ops_crew(self) -> Crew:
        """Creates the top-level hierarchical crew for development and maintenance."""
        orchestrator_agent = Agent(
            role="DevOps Orchestrator",
            goal="Plan, delegate, and oversee all development and maintenance tasks for a specific project based on the provided user request and project configuration.",
            backstory=(
                "You are an expert DevOps engineer specializing in AI-driven automation. "
                "Your job is to manage end-to-end project workflows, delegating tasks to the appropriate "
                "specialized crew (Developer, Documentation, Repo Manager, Research)."
                "You MUST use the loaded project configuration to get necessary details like repository URL, "
                "file paths, and working directories. Always start by reading the repository URL from the configuration "
                "and delegating the clone operation to the Repo Manager."
            ),
            llm=self.router.get_llm_for_task("Manage DevOps workflows"),
            verbose=True,
            allow_delegation=True
        )

        orchestrator_task = Task(
            description=f"""
                Analyze the user request and the project configuration from the YAML file, and break it down into a clear, sequential plan.
                User Request: {self.inputs.get('topic')}.
                The full project configuration is available in the inputs dictionary under the 'config' key.
                The plan should delegate steps to the correct specialist crew and include all necessary context
                like the repository URL, branches, file paths, and the isolated working directory from the configuration.
            """,
            agent=orchestrator_agent,
            expected_output="A list of actions and the specialist crew responsible for each step, with explicit references to the configuration details for that step."
        )

        return Crew(
            agents=[orchestrator_agent],
            tasks=[orchestrator_task],
            process=Process.hierarchical,
            manager_llm=self.router.get_llm_for_task("Manage hierarchical crew"),
            verbose=config.agents.verbose,
            full_output=True,
            crew_delegation=[
                create_developer_crew,
                create_documentation_crew,
                create_repo_manager_crew,
                create_research_crew,
            ]
        )


def run_ai_dev_ops_crew_securely(router: DistributedRouter, project_id: str, inputs: Dict[str, Any]):
    """Secure entry point for the dev ops crew."""
    logger.info(f"Starting secure AI DevOps crew for project '{project_id}'...")
    try:
        manager = AIOpsCrewManager(router, project_id=project_id, inputs=inputs)
        result = manager.execute_crew()
        logger.info("AI DevOps crew execution completed.")
        return result
    except Exception as e:
        logger.error(f"Error instantiating AI DevOps crew manager: {e}", exc_info=True)
        return CrewOutput(tasks_output=[], raw=f"Error: {e}", token_usage=UsageMetrics())


if __name__ == '__main__':
    from your_secure_internal_router_setup import get_router

    router = get_router()
    # Example usage for testing
    project_id = "zeroai"  # Assumes a config file exists for 'zeroai'
    inputs = {
        "topic": "Fix a bug in the code, update documentation, and push changes to the repository.",
        "category": "ai_dev_ops"
    }
    result = run_ai_dev_ops_crew_securely(router, project_id, inputs)
    print("--- Final Result ---")
    print(result)
