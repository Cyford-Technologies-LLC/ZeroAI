# src/tools/git_tool.py
from crewai.tools import BaseTool
import subprocess
import os

class GitTool(BaseTool):
    name: str = "Git Tool"
    description: str = "A tool for performing Git operations like cloning, committing, and pushing."

    def _run(self, command: str, project_path: str, repo_url: str = None, commit_message: str = "Automated commit") -> str:
        """Executes a Git command within a specified project path."""
        try:
            os.chdir(project_path)
            if command == "clone" and repo_url:
                result = subprocess.run(["git", "clone", repo_url, "."], capture_output=True, text=True, check=True)
            elif command == "commit":
                subprocess.run(["git", "add", "."], capture_output=True, text=True, check=True)
                result = subprocess.run(["git", "commit", "-m", commit_message], capture_output=True, text=True, check=True)
            elif command == "push":
                result = subprocess.run(["git", "push"], capture_output=True, text=True, check=True)
            else:
                return "Invalid Git command or missing arguments."
            return result.stdout
        except subprocess.CalledProcessError as e:
            return f"Error executing Git command: {e.stderr}"
        except Exception as e:
            return f"An unexpected error occurred: {e}"

# Make the tool available for agents
git_tool = GitTool()

