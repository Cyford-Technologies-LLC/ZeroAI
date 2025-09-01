# src/crews/internal/tools/git_tool.py

from crewai.tools import BaseTool
import subprocess
from typing import Optional, Any, Dict
import logging

class GitTool(BaseTool):
    # ... (rest of the GitTool class definition)
    name: str = "Git Operator Tool"
    description: str = "Runs Git commands for repository management."
    repo_path: Optional[str] = None

    def _run(self, command: str, repo_path: Optional[str] = None):
        # ... (implementation from before)
        pass # Placeholder for the code

class FileTool(BaseTool):
    # ... (rest of the FileTool class definition)
    name: str = "File System Tool"
    description: str = "Reads and writes to files."

    def _run(self, action: str, path: str, content: Optional[str] = None):
        # ... (implementation from before)
        pass # Placeholder for the code

# --- Add these lines to instantiate the tools ---
# These are the objects that are imported by other modules
git_tool = GitTool()
file_tool = FileTool()


