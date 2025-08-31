import importlib
import inspect
import logging
import sys
import traceback
import uuid
from datetime import datetime
from pathlib import Path
from typing import Dict, Any, List, Optional

from crewai import Agent
from rich.console import Console

# Configure console for rich output
console = Console()

# Define the available agents in the system
AVAILABLE_AGENTS = {
    "Team Manager": {
        "description": "Coordinates tasks and manages teams of specialists",
        "capabilities": ["task coordination", "requirement analysis", "team management", "task delegation",
                         "progress monitoring"]
    },
    "Developer": {
        "description": "Implements code solutions and fixes bugs",
        "capabilities": ["coding", "debugging", "code optimization", "technical design", "unit testing"]
    },
    "Documentation Specialist": {
        "description": "Creates and maintains technical documentation",
        "capabilities": ["technical writing", "API documentation", "user guides", "system diagrams",
                         "markdown expertise"]
    },
    "Testing Engineer": {
        "description": "Designs and implements tests for code quality",
        "capabilities": ["unit testing", "integration testing", "test automation", "QA", "test case design"]
    },
    "Security Analyst": {
        "description": "Analyzes and enhances security measures",
        "capabilities": ["security audits", "vulnerability assessment", "secure coding practices", "threat modeling"]
    },
    "Research Specialist": {
        "description": "Gets all details relating to the project for the team and users. answers all project related questions",
        "capabilities": ["Project Details", "github details", "working directory details", "unknown details"]
    },
    "DevOps Engineer": {
        "description": "Handles deployment and infrastructure automation",
        "capabilities": ["CI/CD pipelines", "containerization", "infrastructure as code", "monitoring setup",
                         "cloud deployment"]
    }
}


class ErrorLogger:
    """Logs errors to a file for later review."""

    def __init__(self):
        self.error_dir = Path("src/errors")
        self.error_dir.mkdir(parents=True, exist_ok=True)

    def log_error(self, error_message: str, context: Dict[str, Any] = None) -> str:
        """
        Log an error to a file for later review.

        Args:
            error_message: The error message to log
            context: Additional context information

        Returns:
            The path to the error log file
        """
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        error_id = str(uuid.uuid4())[:8]
        filename = f"error_{timestamp}_{error_id}.log"
        filepath = self.error_dir / filename

        with open(filepath, 'w') as f:
            f.write(f"ERROR: {error_message}\n")
            f.write(f"TIMESTAMP: {datetime.now().isoformat()}\n")
            f.write(f"ERROR_ID: {error_id}\n\n")

            if context:
                f.write("CONTEXT:\n")
                for key, value in context.items():
                    f.write(f"  {key}: {value}\n")

        console.print(f"📝 Error logged to {filepath}", style="yellow")
        return str(filepath)


def format_agent_list() -> str:
    """Format the list of available agents as a string."""
    agent_list = "# Available Specialist Teams\n\n"

    for name, details in AVAILABLE_AGENTS.items():
        agent_list += f"## {name}\n"
        agent_list += f"- **Description**: {details['description']}\n"
        agent_list += "- **Capabilities**:\n"
        for capability in details['capabilities']:
            agent_list += f"  - {capability}\n"
        agent_list += "\n"

    return agent_list


def discover_available_crews() -> Dict[str, Dict[str, str]]:
    """
    Scan the project to discover available crews and their agents.
    This provides a dynamic list of what's actually accessible.

    Returns:
        A dictionary mapping crew names to details
    """
    available_crews = {}
    errors = []

    # Check the internal crews directory
    crews_path = Path("src/crews/internal")
    if not crews_path.exists() or not crews_path.is_dir():
        error_message = f"Internal crews directory not found at {crews_path}"
        errors.append(error_message)
        console.print(f"⚠️ {error_message}", style="yellow")
        return {"errors": errors}

    # Scan each subdirectory in the internal crews path
    for crew_dir in crews_path.iterdir():
        if crew_dir.is_dir() and crew_dir.name not in ["__pycache__", "team_manager"]:
            crew_name = crew_dir.name
            crew_info = {"path": str(crew_dir)}

            try:
                module_name = f"src.crews.internal.{crew_name}.agents"
                agents_module = importlib.import_module(module_name)

                agent_creators = []
                for name, obj in inspect.getmembers(agents_module):
                    if inspect.isfunction(obj) and name.startswith("create_"):
                        agent_creators.append(name)

                if agent_creators:
                    crew_info["agents"] = agent_creators
                    crew_info["status"] = "available"
                else:
                    crew_info["status"] = "no_agents"
                    crew_info["error"] = "No agent creation functions found"

            except ImportError as e:
                crew_info["status"] = "import_failed"
                crew_info["error"] = str(e)
                error_message = f"Failed to import {module_name}: {e}"
                errors.append(error_message)
            except Exception as e:
                crew_info["status"] = "error"
                crew_info["error"] = str(e)
                error_message = f"Error examining {module_name}: {e}"
                errors.append(error_message)

            available_crews[crew_name] = crew_info

    if errors:
        available_crews["errors"] = errors

    return available_crews


def load_all_coworkers(router: Any, inputs: Dict[str, Any], tools: Optional[List] = None) -> List[Agent]:
    """
    Dynamically loads and instantiates all agents from discovered crews.
    """
    all_coworkers = []
    discovered_crews = discover_available_crews()

    for crew_name, crew_info in discovered_crews.items():
        if crew_info.get("status") == "available":
            module_name = f"src.crews.internal.{crew_name}.agents"
            try:
                agents_module = importlib.import_module(module_name)
                for agent_creator_name in crew_info["agents"]:
                    agent_creator_func = getattr(agents_module, agent_creator_name)
                    new_agent = agent_creator_func(router=router, inputs=inputs, tools=tools)
                    all_coworkers.append(new_agent)
                    console.print(f"✅ Loaded agent: [bold green]{new_agent.name}[/bold green] from {crew_name}",
                                  style="blue")
            except Exception as e:
                console.print(f"❌ Failed to load agent from {module_name}: {e}", style="red")
                traceback.print_exc()

    return all_coworkers


def create_team_manager_agent(router, project_id: str, working_dir: Path) -> Agent:
    """
    Create the Team Manager Agent that only delegates tasks (no direct tools).

    Args:
        router: The LLM router instance
        project_id: The project identifier
        working_dir: The working directory for file operations

    Returns:
        An Agent instance configured as Team Manager
    """
    try:
        # Get LLM for the team manager role
        llm = router.get_llm_for_role("devops_orchestrator")

        # Discover available crews and format for context
        available_crews = discover_available_crews()
        crew_list = format_agent_list()

        console.print(f"👨‍💼 Creating Team Manager agent...", style="blue")

        # Load all potential coworkers
        coworkers = load_all_coworkers(router=router, inputs={})

        # Create the team manager agent with improved instructions
        team_manager = Agent(
            role="Team Manager",
            name="Project Coordinator",
            goal=f"Coordinate specialists to complete tasks for project {project_id} by delegating work efficiently, avoiding redundant questions, and making logical decisions based on coworker feedback.",
            backstory=f"""You are the expert Project Coordinator for project {project_id}. Your role is to analyze tasks, 
            delegate work to appropriate specialists, and oversee the project's progress. You do not perform technical work directly.

            You must follow these instructions precisely:
            1. **Analyze Requirements**: Thoroughly understand the request.
            2. **Identify Specialists**: Determine which available specialists are best suited for each sub-task.
            3. **Prioritize Delegation**: Your primary action should be to use the 'Delegate work to coworker' tool.
            4. **Evaluate Answers**: After asking a question, you MUST evaluate the answer. If the answer provides sufficient information to proceed, delegate the task. Do not ask a chain of repetitive questions. If the answer is vague or unhelpful, formulate a different, more specific question.
            5. **Delegate Effectively**: When delegating, include all relevant context from the original prompt and any prior interactions.
            6. **Integrate Work**: Combine the results from specialists into a cohesive final solution.

            Available Coworkers and their capabilities:
            {crew_list}

            Working directory: {working_dir}
            """,
            llm=llm,
            verbose=True,
            allow_delegation=True,
            coworkers=coworkers,
        )

        return team_manager

    except Exception as e:
        error_logger = ErrorLogger()
        error_logger.log_error(
            f"Error creating team manager agent: {str(e)}",
            {
                "project_id": project_id,
                "working_dir": str(working_dir),
                "traceback": traceback.format_exc(),
            }
        )
        raise e

