# ssrc/crews/internal/tools/docker_tool.py

from crewai.tools import BaseTool
import subprocess
from typing import Optional, Any, Dict
import logging

class DockerTool(BaseTool):
    name: str = "Docker Operator Tool"
    description: str = "Runs Docker commands for sandboxed code execution."

    def _run(self, command: str, **kwargs: Any) -> str:
        """
        Executes a Docker command and returns the output.
        """
        try:
            result = subprocess.run(
                ['docker', 'exec', 'container_name', 'sh', '-c', command],
                capture_output=True, text=True, check=True
            )
            return result.stdout
        except subprocess.CalledProcessError as e:
            logging.error(f"Docker command failed: {e.stderr}")
            return f"Error: {e.stderr}"

# --- Add this line to instantiate the tool ---
# This is the object that is imported by the developer crew
docker_tool = DockerTool()
