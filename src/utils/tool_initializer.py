import os
from typing import Dict, Any, List, Optional
from rich.console import Console

# Use dynamic imports for optional tools to avoid import errors if dependencies are missing
from src.crews.internal.tools.docker_tool import DockerTool
from src.crews.internal.tools.file_tool import FileTool
from crewai_tools import SerperDevTool
from tool_factory import dynamic_github_tool

# Assume config is correctly loaded from the root directory
from src.config import config

console = Console()


def get_universal_tools(inputs: Dict[str, Any], initial_tools: Optional[List] = None) -> List:
    """
    Initializes and returns a list of tools available to all agents.
    Tools are conditionally enabled based on the inputs provided.
    """
    all_tools = initial_tools if initial_tools else []

    working_dir = inputs.get("working_dir")
    repo_path = inputs.get("repository")
    repo_token = inputs.get("repo_token") or inputs.get("repo_token_key")

    # Conditionally add GitTool
    if repo_path and isinstance(repo_path, str) and repo_path.strip() and repo_token:
        try:
            from src.crews.internal.tools.git_tool import GitTool
            all_tools.append(GitTool(repo_path=repo_path))
            console.print("✅ GitTool added to the tool list.", style="green")
        except Exception as e:
            console.print(f"❌ Error adding GitTool: {e}", style="red")
    else:
        console.print(
            "⚠️ Skipping GitTool creation: Missing valid repository URL or authentication token. Git tools will not be available.",
            style="yellow")

    # Add FileTool if a working directory is provided
    if working_dir:
        all_tools.append(FileTool(working_dir=working_dir))

    # Add DockerTool
    all_tools.append(DockerTool())

    # Add SerperDevTool if API key exists
    if config.serper_api_key:
        all_tools.append(SerperDevTool(api_key=config.serper_api_key.get_secret_value()))
    else:
        console.print("⚠️ Skipping SerperDevTool creation: SERPER_API_KEY not found.", style="yellow")

    # Add dynamic GitHub tool
    all_tools.append(dynamic_github_tool)

    return all_tools
