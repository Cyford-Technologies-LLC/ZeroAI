# tool_factory.py

import os
import inspect
from crewai_tools import GithubSearchTool, BaseTool

from typing import Optional, Any
from src.config import config



class DynamicGithubTool(BaseTool):
    name: str = "Dynamic GitHub Search Tool"
    description: str = "Searches a specific GitHub repository using the correct token based on a provided key."

    def _run(self, repo_name: str, token_key: Optional[str] = None, query: str = "") -> str:
        """
        Dynamically creates and runs a GithubSearchTool for a given repository.
        """
        if not token_key:
            return "Error: No token key provided for the GitHub search."

        gh_token = config.github_tokens.get(token_key.lower()) or config.github_tokens.get("general")

        if not gh_token:
            return f"Error: GitHub token not found for key '{token_key}'."

        try:
            # Find the full repository URL based on the repo_name or another identifier
            # This part needs to be updated based on your project config structure
            repo_url = f"https://github.com/{config.ZeroAI['Company_Details']['Projects']['GItHUB_URL'].split('/')[-2]}/{repo_name}"  # Example

            github_tool = GithubSearchTool(
                github_repo=repo_url,
                gh_token=gh_token.get_secret_value()
            )
            return github_tool.run(query=query)
        except Exception as e:
            return f"Error running GitHub tool for {repo_name}: {e}"


# Instantiate the dynamic tool once
dynamic_github_tool = DynamicGithubTool()
