# src/crews/internal/team_manager/tasks.py

from typing import Dict, Any, List, Optional
from crewai import Agent, Task
from pathlib import Path

def create_docker_task(agent: Agent, project_id: str, prompt: str, working_dir: Path) -> Task:
    """
    Create a task for Docker setup.

    Args:
        agent: The agent to assign the task to
        project_id: The project identifier
        prompt: The original user prompt
        working_dir: The working directory

    Returns:
        A Task instance configured for Docker setup
    """
    return Task(
        description=f"""
        TASK: {prompt}

        PROJECT: {project_id}
        WORKING DIRECTORY: {working_dir}

        As a DevOps Engineer specialist, create a Docker environment for testing:

        1. Analyze the requirements for containerizing the project
        2. Create a Dockerfile that builds the appropriate environment
        3. Create a docker-compose.yml file if multiple services are needed
        4. Add any necessary scripts for setup, testing, and execution
        5. Document the Docker setup with clear usage instructions
        6. Verify all required files are created in the working directory

        Focus on creating the actual files needed for Docker containerization.
        All files should be created in: {working_dir}
        """,
        agent=agent,
        expected_output="""
        Complete Docker setup for the project, including:
        1. Dockerfile
        2. docker-compose.yml (if needed)
        3. Any necessary scripts
        4. Setup and usage instructions
        """
    )

def create_project_task(agent: Agent, project_id: str, prompt: str, category: str, working_dir: Path) -> Task:
    """
    Create a general project task based on the category and prompt.

    Args:
        agent: The agent to assign the task to
        project_id: The project identifier
        prompt: The original user prompt
        category: The task category
        working_dir: The working directory

    Returns:
        A Task instance configured for the specified category
    """
    # Define specific instructions based on category
    category_instructions = {
        "developer": """
        As a Developer specialist:
        1. Analyze the code requirements
        2. Implement the necessary code changes
        3. Test your implementation
        4. Document your changes
        """,

        "documentation": """
        As a Documentation Specialist:
        1. Analyze the documentation needs
        2. Create clear, comprehensive documentation
        3. Include examples and diagrams as needed
        4. Ensure all relevant details are covered
        """,

        "testing": """
        As a Testing Engineer:
        1. Analyze the testing requirements
        2. Design appropriate test cases
        3. Implement automated tests
        4. Document test coverage and results
        """,

        "security": """
        As a Security Analyst:
        1. Analyze security requirements and potential vulnerabilities
        2. Implement security best practices
        3. Document security measures
        4. Provide recommendations for future improvements
        """,

        "devops": """
        As a DevOps Engineer:
        1. Analyze infrastructure requirements
        2. Design appropriate automation solutions
        3. Implement CI/CD pipelines or infrastructure as code
        4. Document setup and usage instructions
        """,

        "general": """
        As a Team Manager:
        1. Analyze the task requirements
        2. Determine the most appropriate approach
        3. Execute the task using relevant specialist knowledge
        4. Document the process and results
        """
    }

    # Get the appropriate instructions or default to general
    instructions = category_instructions.get(category.lower(), category_instructions["general"])

    return Task(
        description=f"""
        TASK: {prompt}

        PROJECT: {project_id}
        CATEGORY: {category}
        WORKING DIRECTORY: {working_dir}

        {instructions}

        All output files should be created in: {working_dir}
        """,
        agent=agent,
        expected_output=f"""
        Complete implementation of the requested task for project {project_id}.
        """
    )

def create_agent_listing_task(agent: Agent) -> Task:
    """
    Create a task to list all available agents and their capabilities.

    Args:
        agent: The agent to assign the task to

    Returns:
        A Task instance for listing agents
    """
    return Task(
        description="""
        List all available agents in the system and their capabilities.

        Provide a complete overview of each specialist team, including:
        1. Team name
        2. Description
        3. Detailed capabilities

        Format the response as a well-structured report.
        """,
        agent=agent,
        expected_output="""
        A comprehensive listing of all available agent teams and their capabilities.
        """
    )