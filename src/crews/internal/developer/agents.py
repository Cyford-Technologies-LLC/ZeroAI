# src/crews/internal/developer/agents.py

import os
import inspect
from crewai import Agent
from typing import Dict, Any, Optional, List
from src.utils.memory import Memory
from src.crews.internal.tools.docker_tool import DockerTool
from src.crews.internal.tools.git_tool import GitTool, FileTool
from crewai_tools import SerperDevTool

from distributed_router import DistributedRouter
from src.config import config
from rich.console import Console

# Import the dynamic GitHub tool from the tool factory
from tool_factory import dynamic_github_tool

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


def create_code_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                 coworkers: Optional[List] = None) -> Agent:
    """Create a Code Researcher agent."""
    llm = get_developer_llm(router, category="coding")
    agent_memory = Memory()

    # Pass the dynamic tool instead of a hardcoded instance
    all_tools = (tools if tools else []) + [dynamic_github_tool, SerperDevTool()]

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
        tools=all_tools,
        verbose=config.agents_verbose,
        allow_delegation=True,
        coworkers=coworkers if coworkers is not None else []
    )


def create_junior_developer_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                  coworkers: Optional[List] = None) -> Agent:
    """Create a Junior Developer agent."""
    llm = get_developer_llm(router, category="coding")
    agent_memory = Memory()

    working_dir = inputs.get("working_dir")

    file_tool = FileTool(working_dir=working_dir)
    docker_tool = DockerTool()
    git_tool = GitTool()

    # Pass the dynamic tool instead of a hardcoded instance
    all_tools = (tools if tools else []) + [file_tool, docker_tool, git_tool, dynamic_github_tool]

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
        verbose=config.agents_verbose,
        allow_delegation=False,
        coworkers=coworkers if coworkers is not None else []
    )


def create_senior_developer_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                  coworkers: Optional[List] = None) -> Agent:
    """Create a Senior Developer agent."""
    llm = get_developer_llm(router, category="coding")
    agent_memory = Memory()

    working_dir = inputs.get("working_dir")

    file_tool = FileTool(working_dir=working_dir)
    docker_tool = DockerTool()
    git_tool = GitTool()

    # Pass the dynamic tool instead of a hardcoded instance
    all_tools = (tools if tools else []) + [file_tool, docker_tool, git_tool, dynamic_github_tool]

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
        verbose=config.agents_verbose,
        allow_delegation=True,
        coworkers=coworkers if coworkers is not None else []
    )


def create_qa_engineer_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                             coworkers: Optional[List] = None) -> Agent:
    """Create a QA Engineer agent."""
    llm = get_developer_llm(router, category="qa")
    agent_memory = Memory()

    working_dir = inputs.get("working_dir")
    file_tool = FileTool(working_dir=working_dir)
    docker_tool = DockerTool()

    return Agent(
        role="QA Engineer",
        name="Lara Croft",
        memory=agent_memory,
        learning={
            "enabled": True,
            "learning_rate": 0.05,
            "feedback_incorporation": "immediate",
            "adaptation_strategy": "progressive"
        },
        personality={
            "traits": ["meticulous", "critical", "systematic"],
            "quirks": ["tests edge cases relentlessly", "documents every bug found"],
            "communication_preferences": ["prefers clear test reports", "responds with bug details"]
        },
        communication_style={
            "formality": "professional",
            "verbosity": "descriptive",
            "tone": "objective",
            "technical_level": "intermediate"
        },
        resources=[],
        expertise=[
            "Test Automation", "Performance Testing", "Bug Tracking", "Continuous Integration"
        ],
        goal="Ensure the quality and reliability of code solutions through thorough testing",
        backstory="""An expert in software quality assurance, dedicated to finding and documenting
        defects to ensure a high-quality product. All responses are signed off with 'Lara Croft'""",
        llm=llm,
        tools=(tools if tools else []) + [file_tool, docker_tool],
        verbose=config.agents_verbose,
        allow_delegation=True,
        coworkers=coworkers if coworkers is not None else []
    )
