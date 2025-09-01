# tool_factory.py

import os
from crewai_tools import GithubSearchTool
from crewai.tools import BaseTool

from typing import Optional, Any
from src.config import config # Ensure this is the correct import

class DynamicGithubTool(BaseTool):
    name: str = "Dynamic GitHub Search Tool"
    description: str = "Searches a specific GitHub repository using the correct token based on a provided key."

    def _run(self, repo_name: str, token_key: Optional[str] = None, query: str = "") -> str:
        """
        Dynamically creates and runs a GithubSearchTool for a given repository.
        """
        if not token_key:
            return "Error: No token key provided for the GitHub search."

        # Access the gh_token from the consolidated config
        gh_token_value = config.github_tokens.get(token_key.lower()) or config.github_tokens.get("general")

        if not gh_token_value:
            return f"Error: GitHub token not found for key '{token_key or 'general'}'."

        try:
            # Assuming ZeroAI and Company_Details are at the top level in your settings.yaml or env.
            # Adjust the path based on your final settings.yaml structure.
            company_details = config.Company_Details
            if not company_details:
                return "Error: 'Company_Details' not found in config."

            github_url = company_details.get("Projects", {}).get("GItHUB_URL")
            if not github_url:
                return "Error: 'GItHUB_URL' not found in config under Company_Details.Projects."

            # Construct the repo URL
            repo_url = f"{github_url}/{repo_name}"

            github_tool = GithubSearchTool(
                github_repo=repo_url,
                gh_token=gh_token_value.get_secret_value()
            )
            return github_tool.run(query=query)
        except Exception as e:
            return f"Error running GitHub tool for {repo_name}: {e}"

# Instantiate the dynamic tool once
dynamic_github_tool = DynamicGithubTool()
