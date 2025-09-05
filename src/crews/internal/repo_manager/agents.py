import os
import inspect
from typing import Dict, Any, List, Optional
from crewai import Agent
from crewai.knowledge import DirectoryKnowledgeSource , StringKnowledgeSource
from src.distributed_router import DistributedRouter
from src.utils.shared_knowledge import get_shared_context_for_agent
from rich.console import Console

# Import the custom tools
from src.tools.file_tool import FileTool
from src.utils.memory import Memory

# Assume config is correctly loaded from the root directory
from src.config import config


from src.utils.tool_initializer import get_universal_tools

# Create the console instance so it can be used in this module
console = Console()


def get_repo_manager_llm(router: DistributedRouter, category: str = "repo_management",
                         preferred_models: Optional[List] = None) -> Any:
    # (function implementation remains the same)
    # ...
    llm = router.get_llm_for_role("devops_diagnostician")
    console.print(
        f"🔗 {category.capitalize()} Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")
    return llm


def create_git_operator_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                              coworkers: Optional[List] = None) -> Agent:
    """Create a Git Operator agent."""


    agent_memory = Memory()

    project_location = inputs.get("project_id")
    repository = inputs.get("repository")

    project_knowledge = DirectoryKnowledgeSource(
        directory=f"knowledge/internal_crew/{project_location}"
    )

    # 2. Instantiate StringKnowledgeSource for the repository variable
    repo_knowledge = StringKnowledgeSource(
        content=f"The project's Git repository is located at: {repository}"
    )


    llm = get_repo_manager_llm(router, category="repo_management")

    # Get repository URL from inputs
    repo_path = inputs.get("repository")
    repo_token_key = inputs.get("repo_token_key")
    token = os.getenv(repo_token_key) if repo_token_key else None

    # Instantiate the File tool regardless
    working_dir = inputs.get("working_dir", "/tmp")
    file_tool = FileTool(working_dir=working_dir)

    all_tools = get_universal_tools(inputs, initial_tools=tools)

    # Check for a valid repo path and token before creating the GitTool
    if repo_path and isinstance(repo_path, str) and repo_path.strip() and token:
        try:
            from src.tools.git_tool import GitTool
            git_tool = GitTool(repo_path=repo_path)
            all_tools.append(git_tool)
            console.print("✅ GitTool added to the tool list.", style="green")
        except Exception as e:
            console.print(f"❌ Error adding GitTool: {e}", style="red")
    else:
        console.print(
            "⚠️ Skipping GitTool creation: Missing valid repository URL or authentication token. Git tools will not be available.",
            style="yellow")

    return Agent(
        role="Git Operator",
        name="Deon Sanders",
        memory=agent_memory,
        resources=[],
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
        knowledge_sources=[
            project_knowledge,  # This points to the local directory
            repo_knowledge  # This provides the agent with the repository URL
        ],
        expertise=[
            "GIT", "Bit Bucket"
        ],
        expertise_level=9.2,
        goal="On Project related changes  when the project is complete and tested by team,   the file final rebase can go to branch specified inthe project config .",
        backstory=f"""An automated system for performing repository management tasks.
        
        {get_shared_context_for_agent("Git Operator")}
        
        All responses are signed off with 'Deon Sanders'""",
        llm=llm,
        tools=all_tools,
        verbose=config.agents.verbose,
        allow_delegation=False
    )
