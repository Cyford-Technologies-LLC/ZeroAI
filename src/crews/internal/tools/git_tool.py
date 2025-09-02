from crewai.tools import BaseTool
import subprocess
from typing import Optional, Any, Dict
import logging
from pydantic import ConfigDict, Field

class GitTool(BaseTool):
    # New Pydantic V2 model_config to allow pathlib.Path
    model_config = ConfigDict(arbitrary_types_allowed=True)

    name: str = "Git Operator Tool"
    description: str = "Runs Git commands for repository management."
    repo_path: Optional[str] = Field(default=None)

    def _run(self, command: str, repo_path: Optional[str] = None):
        # ... (implementation from before)
        pass # Placeholder for the code

from pydantic import BaseModel, Field

class FileToolSchema(BaseModel):
    action: str = Field(description="Action to perform: 'read' or 'write'")
    path: str = Field(description="Path to the file")
    content: Optional[str] = Field(None, description="Content for write operations (not needed for read)")

class FileTool(BaseTool):
    # New Pydantic V2 model_config to allow pathlib.Path
    model_config = ConfigDict(arbitrary_types_allowed=True)
    name: str = "File System Tool"
    description: str = "Reads and writes to files."
    args_schema: type[BaseModel] = FileToolSchema

    def _run(self, action: str, path: str, content: Optional[str] = None):
        import os
        full_path = os.path.normpath(path)
        if action == "read":
            try:
                with open(full_path, 'r', encoding='utf-8') as f:
                    return f.read()
            except FileNotFoundError:
                return "File not found."
            except Exception as e:
                return f"Error reading file: {e}"
        elif action == "write" and content:
            try:
                os.makedirs(os.path.dirname(full_path), exist_ok=True)
                with open(full_path, 'w', encoding='utf-8') as f:
                    f.write(content)
                return "File written successfully."
            except Exception as e:
                return f"Error writing file: {e}"
        else:
            return "Invalid file operation or missing content."

# Add to src/crews/internal/tools/git_tool.py
def create_git_tool(repo_path: str):
    return GitTool(repo_path=repo_path)
# --- Add these lines to instantiate the tools ---
# These are the objects that are imported by other modules
file_tool = FileTool()
