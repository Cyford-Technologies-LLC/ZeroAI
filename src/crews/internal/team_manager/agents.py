# src/crews/internal/team_manager/agents.py
import importlib
import inspect
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
    def __init__(self):
        self.error_dir = Path("errors")
        self.error_dir.mkdir(parents=True, exist_ok=True)

    def log_error(self, error_message: str, context: Dict[str, Any] = None) -> str:
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
    available_crews = {}
    errors = []
    crews_path = Path("src/crews/internal")
    if not crews_path.exists() or not crews_path.is_dir():
        error_message = f"Internal crews directory not found at {crews_path}"
        errors.append(error_message)
        console.print(f"⚠️ {error_message}", style="yellow")
        return {"errors": errors}

    for crew_dir in crews_path.iterdir():
        if crew_dir.is_dir() and crew_dir.name not in ["__pycache__", "team_manager", "diagnostics"]:
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
    discovered_crews = discover_available_crews()
    agent_creator_functions = {}
    for crew_name, crew_info in discovered_crews.items():
        if crew_info.get("status") == "available":
            module_name = f"src.crews.internal.{crew_name}.agents"
            try:
                agents_module = importlib.import_module(module_name)
                for agent_creator_name in crew_info["agents"]:
                    agent_creator_functions[agent_creator_name] = getattr(agents_module, agent_creator_name)
            except Exception as e:
                console.print(f"❌ Failed to get agent creators from {module_name}: {e}", style="red")

    temp_coworkers = []
    for creator_func in agent_creator_functions.values():
        temp_agent = creator_func(router=router, inputs=inputs, tools=tools, coworkers=[])
        temp_coworkers.append(temp_agent)

    all_coworkers = []
    for creator_func in agent_creator_functions.values():
        new_agent = creator_func(router=router, inputs=inputs, tools=tools, coworkers=temp_coworkers)
        all_coworkers.append(new_agent)
        console.print(f"✅ Configured agent: [bold green]{new_agent.name}[/bold green] with coworkers", style="blue")
    return all_coworkers


def create_team_manager_agent(router, project_id: str, working_dir: Path, coworkers: Optional[List] = None) -> Agent:
    """
    Creates and configures the Team Manager Agent.

    Args:
        router: The DevOps router instance for LLM routing.
        project_id: The ID of the project being worked on.
        working_dir: The project's working directory.
        coworkers: A list of all other agents in the crew for delegation.

    Returns:
        The configured CrewAI Team Manager Agent.
    """
    try:
        llm = router.get_llm_for_role("devops_orchestrator")
        available_crews = discover_available_crews()
        crew_list = format_agent_list()

        console.print(f"👨‍💼 Creating Team Manager agent...", style="blue")

        team_manager = Agent(
            role="Team Manager",
            name="Project Coordinator",
            goal=f"Coordinate specialists to complete tasks for project {project_id} by delegating work efficiently, avoiding redundant questions, and making logical decisions based on coworker feedback.",
            backstory=f"""You are the expert Project Coordinator for project {project_id}. Your role is to analyze tasks, 
            delegate work to appropriate specialists, and oversee the project's progress. You do not perform technical work directly.

            You must follow these instructions precisely:
            1. **Analyze the task**: Before delegating, carefully review the user's request and project context.
            2. **Identify the right specialist**: Examine the list of `Available Specialist Teams` below and their `Capabilities`. Select the agent best suited for the current task.
            3. **Delegate the task**: Formulate a clear and concise task for the chosen specialist.
            4. **Avoid redundancy**: Do not ask specialists for information already available in the prompt or their previous outputs.
            5. **Manage project state**: Use your tool to set project properties as needed.

            # Available Specialist Teams
            {crew_list}
            """,
            llm=llm,
            tools=[],  # Assuming tools are added elsewhere if needed
            verbose=True,
            max_iter=15,
            coworkers=coworkers,
            allow_delegation=True
        )
        return team_manager

    except Exception as e:
        console.print(f"❌ Error creating Team Manager agent: {e}", style="red")
        error_logger = ErrorLogger()
        error_logger.log_error(f"Error creating Team Manager agent", {"error": str(e), "traceback": traceback.format_exc()})
        sys.exit(1)

