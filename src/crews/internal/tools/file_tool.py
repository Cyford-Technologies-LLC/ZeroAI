# src/tools/file_tool.py
from crewai.tools import BaseTool
from pydantic import BaseModel, Field
from typing import Optional
import os

class FileToolInput(BaseModel):
    operation: str = Field(description="Operation to perform: 'read' or 'write'")
    file_path: str = Field(description="Path to the file")
    path: str = Field(description="Path to the file (alias for file_path)")
    content: Optional[str] = Field(None, description="Content for write operations (not needed for read)")
    
    def __init__(self, **data):
        # Handle both 'path' and 'file_path' parameters
        if 'path' in data and 'file_path' not in data:
            data['file_path'] = data['path']
        elif 'file_path' in data and 'path' not in data:
            data['path'] = data['file_path']
        super().__init__(**data)

class FileTool(BaseTool):
    name: str = "File Tool"
    description: str = "A tool for performing file operations like reading and writing."
    args_schema: type[BaseModel] = FileToolInput

    def _run(self, operation: str, file_path: str = None, path: str = None, content: Optional[str] = None) -> str:
        # Use either file_path or path parameter
        actual_path = file_path or path
        if not actual_path:
            return "Error: No file path provided."
        """Executes file operations."""
        full_path = os.path.normpath(actual_path)
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
