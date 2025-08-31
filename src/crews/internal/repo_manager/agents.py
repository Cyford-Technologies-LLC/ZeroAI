from crewai import Agent
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from tools.git_tool import git_tool, file_tool
from src.utils.memory import Memory
from rich.console import Console

# Create the console instance so it can be used in this module
console = Console()

def create_git_operator_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: List[Any]) -> Agent:
    task_description = "Perform Git and file system operations."
    # The get_llm_for_task method likely no longer supports preferred_models as a positional argument.
    # We will remove it and let the router handle preferences internally or in a different way.
    # A more robust solution would be to check the router's method signature.

    agent_memory = Memory()

    # Try to get LLM with fallback
    llm = None
    try:
        # Use the updated get_llm_for_task with just the task description
        # This is based on the error "takes 2 positional arguments but 3 were given".
        llm = router.get_llm_for_task(task_description)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for repo manager agent via router: {e}", style="yellow")
        # Fall back to local model
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError("Failed to get LLM for repo manager agent after all attempts.")

    console.print(
        f"🔗 Repo Manager Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    # Remove the second, redundant call to get_llm_for_task

    return Agent(
        role="Git Operator",
        name="Deon Sanders",
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
            "GIT", "Bit Bucket"
        ],
        expertise_level=9.2,
        goal="Execute Git commands and file manipulations to manage project repositories.",
        backstory="An automated system for performing repository management tasks.",
        llm=llm,
        tools=tools, # Use the tools passed to the function
        verbose=config.agents.verbose,
        allow_delegation=False
    )
