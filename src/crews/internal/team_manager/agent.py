# src/crews/internal/team_manager/agent.py

import logging
import uuid
from pathlib import Path
from typing import Dict, Any, List, Optional
from datetime import datetime
from rich.console import Console
from crewai import Agent
from src.utils.memory import Memory

# Configure console for rich output
console = Console()

# Define the available agents in the system
AVAILABLE_AGENTS = {
    "Team Manager": {
        "description": "Coordinates tasks and manages teams of specialists",
        "capabilities": ["task coordination", "requirement analysis", "team management", "task delegation", "progress monitoring"]
    },
    "Developer": {
        "description": "Implements code solutions and fixes bugs",
        "capabilities": ["coding", "debugging", "code optimization", "technical design", "unit testing"]
    },
    "Documentation Specialist": {
        "description": "Creates and maintains technical documentation",
        "capabilities": ["technical writing", "API documentation", "user guides", "system diagrams", "markdown expertise"]
    },
    "Testing Engineer": {
        "description": "Designs and implements tests for code quality",
        "capabilities": ["unit testing", "integration testing", "test automation", "QA", "test case design"]
    },
    "Security Analyst": {
        "description": "Analyzes and enhances security measures",
        "capabilities": ["security audits", "vulnerability assessment", "secure coding practices", "threat modeling"]
    },
    "DevOps Engineer": {
        "description": "Handles deployment and infrastructure automation",
        "capabilities": ["CI/CD pipelines", "containerization", "infrastructure as code", "monitoring setup", "cloud deployment"]
    }
}

class ErrorLogger:
    """Logs errors to a file for later review."""

    def __init__(self):
        self.error_dir = Path("errors")
        self.error_dir.mkdir(exist_ok=True)

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

def create_team_manager_agent(router, project_id: str, working_dir: Path, tools: List = None) -> Agent:
    """
    Create the Team Manager Agent that executes and coordinates tasks.

    Args:
        router: The LLM router instance
        project_id: The project identifier
        working_dir: The working directory for file operations
        tools: List of tools available to the agent

    Returns:
        An Agent instance configured as Team Manager
    """
    try:
        # Get LLM for the team manager role
        llm = router.get_llm_for_role("devops_orchestrator")  # Reusing existing role for compatibility

        # Create a dedicated memory instance for the team manager
        team_manager_memory = Memory(max_items=2000)

        console.print(f"👨‍💼 Creating Team Manager agent with dedicated memory...", style="blue")

        # Format the list of available agents
        agent_list = format_agent_list()

        # Create the team manager agent
        team_manager = Agent(
            role="Team Manager",
            name="Project Coordinator",
            memory=team_manager_memory,
            goal=f"Complete tasks for project {project_id} by utilizing specialist expertise",
            backstory=f"""I am the Team Manager for project {project_id}. I coordinate tasks and
            ensure they're completed effectively by working with specialist teams when needed.

            {agent_list}

            When tasked with a challenge, I first analyze what needs to be done and which specialists
            would be most suitable. I ensure all requirements are clear and that work is executed
            to the highest quality standards.

            I maintain clear communication about progress and any challenges encountered. I'm
            responsible for ensuring the final deliverables meet all requirements and are properly
            documented.

            Working directory: {working_dir}
            """,
            llm=llm,
            tools=tools or [],
            verbose=True,
            allow_delegation=False  # Direct execution, no delegation
        )

        return team_manager

    except Exception as e:
        error_logger = ErrorLogger()
        error_logger.log_error(
            f"Error creating team manager agent: {str(e)}",
            {"project_id": project_id, "working_dir": str(working_dir)}
        )
        console.print(f"❌ Error creating team manager agent: {e}", style="red")
        raise