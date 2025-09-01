# src/crews/internal/repo_manager/agents.py

from crewai import Agent
from typing import Dict, Any, List, Optional
from distributed_router import DistributedRouter
from config import config
# FIX: Correct the import path for GitTool and FileTool
from src.tools.git_tool import GitTool, FileTool
from src.utils.memory import Memory
from rich.console import Console
from src.tools.git_tool import GitTool

# Create the console instance so it can be used in this module
console = Console()


def create_git_operator_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: List[Any],
                              coworkers: Optional[List] = None) -> Agent:
    task_description = "Perform Git and file system operations."

    agent_memory = Memory()

    # Try to get LLM with fallback
    llm = None
    try:
        llm = router.get_llm_for_task(task_description)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for repo manager agent via router: {e}", style="yellow")
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError("Failed to get LLM for repo manager agent after all attempts.")

    console.print(
        f"🔗 Repo Manager Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    # Get the working directory from inputs for dynamic tool instantiation
    working_dir = inputs.get("working_dir", "/tmp")

    # FIX: Instantiate the Git and File tools dynamically
    git_tool = GitTool(repo_path=working_dir)
    file_tool = FileTool(working_dir=working_dir)
    github_tool = GitHubTool(github_repo=inputs.get("repository"))

    # FIX: Combine all tools into a single list
    all_tools = [git_tool, file_tool, github_tool]

    return Agent(
        role="Git Operator",
        name="Deon Sanders",
        memory=agent_memory,
        coworkers=coworkers if coworkers is not None else [],
        learning={
            "enabled": True,
            "learning_rate": 0.05,
            "feedback_incorporation": "immediate",
            "adaptation_strategy": "progressive"
        },
        personality={
            "traits": ["precise", "efficient", "methodical", "detail-oriented"],
            "quirks": ["prefers command-line interfaces", "avoids unnecessary conversation"],
            "communication_preferences": ["prefers direct commands", "responds with confirmation"]
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
        backstory="""An automated system for performing repository management tasks. All responses are signed off with 'Deon Sanders'""",
        llm=llm,
        tools=all_tools,  # FIX: Pass the new list of all tools
        verbose=config.agents.verbose,
        allow_delegation=False
    )
