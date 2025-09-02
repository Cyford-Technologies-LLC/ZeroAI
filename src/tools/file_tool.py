# src/tools/file_tool.py
from crewai.tools import BaseTool
from pydantic import Field
from typing import Optional
import os

class FileTool(BaseTool):
    name: str = "File Tool"
    description: str = "A tool for performing file operations like reading and writing."

    def _run(self, operation: str, file_path: str, content: Optional[str] = Field(None, description="Content for write operations")) -> str:
        """Executes file operations."""
        full_path = os.path.normpath(file_path)
        if operation == "read":
            try:
                with open(full_path, 'r', encoding='utf-8') as f:
                    return f.read()
            except FileNotFoundError:
                return "File not found."
            except Exception as e:
                return f"Error reading file: {e}"
        elif operation == "write" and content:
            try:
                os.makedirs(os.path.dirname(full_path), exist_ok=True)
                with open(full_path, 'w', encoding='utf-8') as f:
                    f.write(content)
                return "File written successfully."
            except Exception as e:
                return f"Error writing file: {e}"
        else:
            return "Invalid file operation or missing content."

# Make the tool available for agents
file_tool = FileTool()
