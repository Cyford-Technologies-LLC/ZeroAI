import os
import inspect
from crewai import Agent
from typing import Dict, Any, List, Optional
from distributed_router import DistributedRouter
from config import config
from src.tools.git_tool import GitTool, FileTool
from src.utils.memory import Memory
from rich.console import Console
# FIX: Import the correct GitHub tool from crewai_tools
from crewai_tools import GithubSearchTool


# Create the console instance so it can be used in this module
console = Console()

def get_repo_manager_llm(router: DistributedRouter, category: str = "repo_management",
                         preferred_models: Optional[List] = None) -> Any:
    """
    Selects the optimal LLM for the repo manager agent.
    """
    preferred_models = preferred_models or ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llama3.2:1b"]

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

def create_git_operator_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: List[Any],
                              coworkers: Optional[List] = None) -> Agent:
    """Create a Git Operator agent."""
    task_description = "Perform Git and file system operations."

    agent_memory = Memory()

    llm = get_repo_manager_llm(router, category="repo_management")

    # Get working directory and repository from inputs
    working_dir = inputs.get("working_dir", "/tmp")
    repository = inputs.get("repository")

    # Instantiate the Git, File, and GithubSearch tools
    git_tool = GitTool(repo_path=working_dir)
    file_tool = FileTool(working_dir=working_dir)
    # FIX: Use the correctly imported GithubSearchTool
    github_tool = GithubSearchTool(github_repo=repository)

    # Combine all tools, including any external tools passed in
    all_tools = (tools or []) + [git_tool, file_tool, github_tool]

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
        tools=all_tools,
        verbose=config.agents.verbose,
        allow_delegation=False
    )
