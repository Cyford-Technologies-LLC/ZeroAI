# src/crews/internal/developer/agents.py

import os
import inspect
from crewai import Agent
from typing import Dict, Any, Optional, List
from src.utils.memory import Memory
from src.tools.git_tool import GitTool, FileTool, DockerTool
from distributed_router import DistributedRouter
from config import config
from rich.console import Console

# Important: for any crews outside the default, make sure the proper crews are loaded
os.environ["CREW_TYPE"] = "internal"
console = Console()

def get_developer_llm(router: DistributedRouter, category: str = "coding") -> Any:
    """
    Selects the optimal LLM based on preferences for developer tasks,
    with a fallback mechanism.
    """
    preferred_models = ["codellama:13b", "llama3.1:8b", "llama3.2:latest", "llama3.2:1b"]

    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference(category)
        if category_model and category_model not in preferred_models:
            preferred_models.insert(0, category_model)
    except ImportError:
        pass

    llm = None
    try:
        task_description = f"Perform {category} tasks."
        llm = router.get_llm_for_task(task_description)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for {category} agent via router: {e}", style="yellow")
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError(f"Failed to get LLM for {category} agent after all attempts.")

    console.print(
        f"🔗 {category.capitalize()} Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")
    return llm

def create_code_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None, coworkers: Optional[List] = None) -> Agent:
    """Create a Code Researcher agent."""
    llm = get_developer_llm(router, category="coding")
    agent_memory = Memory()

    # FIX: Get repository from inputs and instantiate GitHubTool
    repository = inputs.get("repository")
    github_tool = GitHubTool(github_repo=repository)

    # FIX: Add GitHubTool to the tools list
    all_tools = (tools if tools else []) + [github_tool]

    return Agent(
        role="Code Researcher",
        name="Dr. Alan Parse",
        memory=agent_memory,
        learning={
            "enabled": True,
            "learning_rate": 0.05,
            "feedback_incorporation": "immediate",
            "adaptation_strategy": "progressive"
        },
        personality={
            "traits": ["analytical", "detail-oriented", "methodical"],
            "quirks": ["always cites research papers", "uses scientific analogies"],
            "communication_preferences": ["prefers direct questions", "responds with examples"]
        },
        communication_style={
            "formality": "professional",
            "verbosity": "concise",
            "tone": "authoritative",
            "technical_level": "expert"
        },
        resources=[
            "testing_frameworks.md",
            "code_quality_guidelines.pdf",
            "https://testing-best-practices.com"
        ],
        expertise=[
            "Python", "JavaScript", "Database Design",
            "API Development", "Microservices Architecture", "PHP", "JavaScript"
        ],
        expertise_level=9.2,
        goal="Research and understand code patterns and issues",
        backstory="""You are an expert at analyzing codebases, understanding
        complex systems, and identifying potential issues. All responses are signed off with 'Dr. Alan Parse'""",
        llm=llm,
        tools=all_tools, # FIX: Pass the combined tools list
        verbose=True,
        allow_delegation=True,
        coworkers=coworkers if coworkers is not None else []
    )

def create_junior_developer_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None, coworkers: Optional[List] = None) -> Agent:
    """Create a Junior Developer agent."""
    llm = get_developer_llm(router, category="coding")
    agent_memory = Memory()

    working_dir = inputs.get("working_dir")
    repository = inputs.get("repository")

    file_tool = FileTool(working_dir=working_dir)
    docker_tool = DockerTool()
    github_tool = GitHubTool(github_repo=repository) # FIX: Instantiate GitHubTool

    all_tools = (tools if tools else []) + [file_tool, docker_tool, github_tool] # FIX: Add GitHubTool

    return Agent(
        role="Junior Developer",
        name="Tom Kyles",
        memory=agent_memory,
        learning={
            "enabled": True,
            "learning_rate": 0.05,
            "feedback_incorporation": "immediate",
            "adaptation_strategy": "progressive"
        },
        personality={
            "traits": ["analytical", "detail-oriented", "methodical"],
            "quirks": ["always cites research papers", "uses scientific analogies"],
            "communication_preferences": ["prefers direct questions", "responds with examples"]
        },
        communication_style={
            "formality": "professional",
            "verbosity": "concise",
            "tone": "authoritative",
            "technical_level": "expert"
        },
        resources=[
            "testing_frameworks.md",
            "code_quality_guidelines.pdf",
            "https://testing-best-practices.com"
        ],
        goal="Implement high-quality code solutions under guidance",
        backstory="""You are a junior software developer, eager to learn and implement code solutions
        under the guidance of senior team members. All responses are signed off with 'Tom Kyles'""",
        llm=llm,
        tools=all_tools,
        verbose=True,
        allow_delegation=False,
        coworkers=coworkers if coworkers is not None else []
    )

def create_senior_developer_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None, coworkers: Optional[List] = None) -> Agent:
    """Create a Senior Developer agent."""
    llm = get_developer_llm(router, category="coding")
    agent_memory = Memory()

    working_dir = inputs.get("working_dir")
    repository = inputs.get("repository")

    file_tool = FileTool(working_dir=working_dir)
    docker_tool = DockerTool()
    github_tool = GitHubTool(github_repo=repository) # FIX: Instantiate GitHubTool

    all_tools = (tools if tools else []) + [file_tool, docker_tool, github_tool] # FIX: Add GitHubTool

    return Agent(
        role="Senior Developer",
        name="Tony Kyles",
        memory=agent_memory,
        learning={
            "enabled": True,
            "learning_rate": 0.05,
            "feedback_incorporation": "immediate",
            "adaptation_strategy": "progressive"
        },
        personality={
            "traits": ["analytical", "detail-oriented", "methodical"],
            "quirks": ["always cites research papers", "uses scientific analogies"],
            "communication_preferences": ["prefers direct questions", "responds with examples"]
        },
        communication_style={
            "formality": "professional",
            "verbosity": "concise",
            "tone": "authoritative",
            "technical_level": "expert"
        },
        resources=[
            "testing_frameworks.md",
            "code_quality_guidelines.pdf",
            "https://testing-best-practices.com"
        ],
        goal="Implement high-quality, robust code solutions to complex problems",
        backstory="""You are a skilled software developer with years of experience.
        You create elegant, maintainable, and robust code solutions to complex problems. All responses are signed off with 'Tony Kyles'""",
        llm=llm,
        tools=all_tools,
        verbose=True,
        allow_delegation=True,
        coworkers=coworkers if coworkers is not None else []
    )

def create_qa_engineer_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None, coworkers: Optional[List] = None) -> Agent:
    """Create a QA Engineer agent."""
    llm = get_developer_llm(router, category="testing")
    agent_memory = Memory()

    working_dir = inputs.get("working_dir")
    repository = inputs.get("repository")

    file_tool = FileTool(working_dir=working_dir)
    github_tool = GitHubTool(github_repo=repository) # FIX: Instantiate GitHubTool

    all_tools = (tools if tools else []) + [file_tool, github_tool] # FIX: Add GitHubTool

    return Agent(
        role="QA Engineer",
        name="Anthony Gates",
        memory=agent_memory,
        learning={
            "enabled": True,
            "learning_rate": 0.05,
            "feedback_incorporation": "immediate",
            "adaptation_strategy": "progressive"
        },
        personality={
            "traits": ["analytical", "detail-oriented", "methodical"],
            "quirks": ["always cites research papers", "uses scientific analogies"],
            "communication_preferences": ["prefers direct questions", "responds with examples"]
        },
        communication_style={
            "formality": "professional",
            "verbosity": "concise",
            "tone": "authoritative",
            "technical_level": "expert"
        },
        resources=[
            "testing_frameworks.md",
            "code_quality_guidelines.pdf",
            "https://testing-best-practices.com"
        ],
        goal="Ensure the quality and correctness of implemented code through rigorous testing and validation.",
        backstory="""A meticulous QA engineer dedicated to identifying defects, writing comprehensive test cases, and ensuring the software meets
        all quality standards. All responses are signed off with 'Anthony Gates'""",
        llm=llm,
        tools=all_tools,
        verbose=True,
        allow_delegation=False,
        coworkers=coworkers if coworkers is not None else []
    )
