# src/crews/internal/tool_factory.py

from crewai_tools import GithubSearchTool
from config import config


def create_github_search_tool(repo_name: str) -> GithubSearchTool:
    """
    Dynamically creates and returns a GithubSearchTool for a specific repository.
    """
    gh_token = config.github_tokens.get(repo_name.lower()) or config.github_tokens.get("general")

    if not gh_token:
        raise ValueError(f"GH_TOKEN not found for repository '{repo_name}'.")

    # Find the matching repository from the config
    for repo_cfg in config.github_repos:
        if repo_cfg.name.lower() == repo_name.lower():
            repo_full_name = f"{repo_cfg.owner}/{repo_cfg.name}"
            return GithubSearchTool(github_repo=repo_full_name, gh_token=gh_token.get_secret_value())

    raise ValueError(f"Repository '{repo_name}' not found in configuration.")
