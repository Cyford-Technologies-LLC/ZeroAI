# tool_factory.py

import os
from typing import Optional, Any
from crewai.tools import BaseTool

# Import with error handling
try:
    from crewai_tools import GithubSearchTool
    GITHUB_TOOL_AVAILABLE = True
except ImportError:
    GITHUB_TOOL_AVAILABLE = False

try:
    from src.config import config
    CONFIG_AVAILABLE = True
except ImportError:
    CONFIG_AVAILABLE = False

class DynamicGithubTool(BaseTool):
    name: str = "Dynamic GitHub Search Tool"
    description: str = "Searches a specific GitHub repository using the correct token based on a provided key."

    def _run(self, repo_name: str, token_key: Optional[str] = None, query: str = "") -> str:
        """
        Dynamically creates and runs a GithubSearchTool for a given repository.
        """
        # Check if GitHub tool is available
        if not GITHUB_TOOL_AVAILABLE:
            return "Error: GithubSearchTool not available. Please install crewai_tools."
        
        # Check if config is available
        if not CONFIG_AVAILABLE:
            return "Error: Configuration not available. Cannot access GitHub tokens."
        
        # Auto-detect token key from config if not provided
        if not token_key:
            if hasattr(config, 'Company_Details') and config.Company_Details:
                company_details = config.Company_Details
                if isinstance(company_details, dict):
                    projects = company_details.get("Projects", {})
                    if isinstance(projects, dict):
                        configured_key = projects.get("GIT_TOKEN_KEY", "")
                        # Remove curly braces if present: {GH_TOKEN_CYFORD} -> GH_TOKEN_CYFORD
                        if configured_key.startswith("{") and configured_key.endswith("}"):
                            token_key = configured_key[1:-1]
                        else:
                            token_key = configured_key
        
        if not token_key:
            return "Error: No token key provided and none configured in Company_Details.Projects.GIT_TOKEN_KEY."

        try:
            # Access the gh_token from the consolidated config
            gh_token_value = None
            if hasattr(config, 'github_tokens') and config.github_tokens:
                # Try exact match, then lowercase, then general
                gh_token_value = (config.github_tokens.get(token_key) or 
                                config.github_tokens.get(token_key.lower()) or
                                config.github_tokens.get("general"))
            
            # Fallback to direct environment variable lookup
            if not gh_token_value:
                gh_token_value = os.getenv(token_key) or os.getenv("GITHUB_TOKEN")
            
            if not gh_token_value:
                return f"Error: GitHub token not found for key '{token_key or 'general'}'. Please set GITHUB_TOKEN environment variable."

            # Get GitHub URL from config or use default
            github_url = "https://github.com"
            if hasattr(config, 'Company_Details') and config.Company_Details:
                company_details = config.Company_Details
                if isinstance(company_details, dict):
                    projects = company_details.get("Projects", {})
                    if isinstance(projects, dict):
                        github_url = projects.get("GItHUB_URL", github_url)

            # Construct the repo URL
            if not repo_name.startswith("http"):
                repo_url = f"{github_url}/{repo_name}"
            else:
                repo_url = repo_name

            # Extract token value if it's a SecretStr
            if hasattr(gh_token_value, 'get_secret_value'):
                token_str = gh_token_value.get_secret_value()
            else:
                token_str = str(gh_token_value)

            github_tool = GithubSearchTool(
                github_repo=repo_url,
                gh_token=token_str
            )
            return github_tool.run(query=query)
            
        except Exception as e:
            return f"Error running GitHub tool for {repo_name}: {e}"

# Create a safe instance that handles all error cases
def create_dynamic_github_tool():
    """Factory function to create DynamicGithubTool with error handling"""
    try:
        return DynamicGithubTool()
    except Exception as e:
        # Return a dummy tool if creation fails
        class DummyGithubTool(BaseTool):
            name: str = "GitHub Search (Unavailable)"
            description: str = "GitHub search tool is not available due to missing dependencies."
            
            def _run(self, *args, **kwargs) -> str:
                return f"GitHub search tool unavailable: {e}"
        
        return DummyGithubTool()

# Instantiate the dynamic tool with error handling
dynamic_github_tool = create_dynamic_github_tool()