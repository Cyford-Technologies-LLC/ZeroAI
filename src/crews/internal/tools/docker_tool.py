# src/crews/internal/tools/docker_tool.py

from crewai.tools import BaseTool
import subprocess
import json
import time
from typing import Optional, Any, Dict, List
from pydantic import BaseModel, Field
import logging


class DockerToolSchema(BaseModel):
    action: str = Field(
        description="Action: 'run', 'exec', 'stop', 'remove', 'list', 'logs', 'network', 'volume', 'compose'")
    image: Optional[str] = Field(None, description="Docker image name (for run action)")
    container: Optional[str] = Field(None, description="Container name or ID")
    command: Optional[str] = Field(None, description="Command to execute")
    ports: Optional[str] = Field(None, description="Port mapping (e.g., '8080:80')")
    volumes: Optional[str] = Field(None, description="Volume mapping (e.g., '/host:/container')")
    environment: Optional[str] = Field(None, description="Environment variables (e.g., 'KEY=value')")
    network: Optional[str] = Field(None, description="Docker network name")
    compose_file: Optional[str] = Field(None, description="Path to the docker-compose.yml file")
    service: Optional[str] = Field(None, description="Service name within the compose file (for exec action)")
    detach: bool = Field(True, description="Run in detached mode")


class DockerTool(BaseTool):
    name: str = "Docker Operator Tool"
    description: "Enhanced Docker tool for container management, networking, and orchestration."
    args_schema: type[BaseModel] = DockerToolSchema

    def _run(self, action: str, image: Optional[str] = None, container: Optional[str] = None,
             command: Optional[str] = None, ports: Optional[str] = None, volumes: Optional[str] = None,
             environment: Optional[str] = None, network: Optional[str] = None, detach: bool = True,
             compose_file: Optional[str] = None, service: Optional[str] = None) -> str:
        """
        Enhanced Docker operations with container management capabilities.
        """
        try:
            if action == "run":
                return self._run_container(image, command, ports, volumes, environment, network, detach)
            elif action == "exec":
                return self._exec_container(container, command)
            elif action == "stop":
                return self._stop_container(container)
            elif action == "remove":
                return self._remove_container(container)
            elif action == "list":
                return self._list_containers()
            elif action == "logs":
                return self._get_logs(container)
            elif action == "network":
                return self._manage_network(command, network)
            elif action == "volume":
                return self._manage_volume(command, volumes)
            elif action == "compose":
                return self._manage_compose(command, compose_file, service)
            else:
                return f"Unknown action: {action}. Available: run, exec, stop, remove, list, logs, network, volume, compose"
        except Exception as e:
            logging.error(f"Docker operation failed: {str(e)}")
            return f"Error: {str(e)}"

    def _run_container(self, image: str, command: Optional[str] = None, ports: Optional[str] = None,
                       volumes: Optional[str] = None, environment: Optional[str] = None,
                       network: Optional[str] = None, detach: bool = True) -> str:
        """Run a new container with specified configuration."""
        if not image:
            return "Error: Image name is required for run action"

        cmd = ["docker", "run"]

        if detach:
            cmd.append("-d")

        if ports:
            cmd.extend(["-p", ports])

        if volumes:
            cmd.extend(["-v", volumes])

        if environment:
            for env in environment.split(","):
                cmd.extend(["-e", env.strip()])

        if network:
            cmd.extend(["--network", network])

        # Generate container name
        container_name = f"zeroai-{image.replace(':', '-').replace('/', '-')}-{int(time.time())}"
        cmd.extend(["--name", container_name])

        cmd.append(image)

        if command:
            cmd.extend(command.split())

        result = subprocess.run(cmd, capture_output=True, text=True, check=True)
        return f"Container '{container_name}' started successfully. ID: {result.stdout.strip()}"

    def _exec_container(self, container: str, command: str) -> str:
        """Execute command in running container."""
        if not container or not command:
            return "Error: Container name and command are required for exec action"

        cmd = ["docker", "exec", container, "sh", "-c", command]
        result = subprocess.run(cmd, capture_output=True, text=True, check=True)
        return result.stdout

    def _stop_container(self, container: str) -> str:
        """Stop running container."""
        if not container:
            return "Error: Container name is required for stop action"

        cmd = ["docker", "stop", container]
        result = subprocess.run(cmd, capture_output=True, text=True, check=True)
        return f"Container '{container}' stopped successfully"

    def _remove_container(self, container: str) -> str:
        """Remove container."""
        if not container:
            return "Error: Container name is required for remove action"

        cmd = ["docker", "rm", "-f", container]
        result = subprocess.run(cmd, capture_output=True, text=True, check=True)
        return f"Container '{container}' removed successfully"

    def _list_containers(self) -> str:
        """List all containers."""
        cmd = ["docker", "ps", "-a", "--format", "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}"]
        result = subprocess.run(cmd, capture_output=True, text=True, check=True)
        return result.stdout

    def _get_logs(self, container: str) -> str:
        """Get container logs."""
        if not container:
            return "Error: Container name is required for logs action"

        cmd = ["docker", "logs", "--tail", "50", container]
        result = subprocess.run(cmd, capture_output=True, text=True, check=True)
        return result.stdout

    def _manage_network(self, command: str, network: Optional[str] = None) -> str:
        """Manage Docker networks."""
        if command == "create" and network:
            cmd = ["docker", "network", "create", network]
            result = subprocess.run(cmd, capture_output=True, text=True, check=True)
            return f"Network '{network}' created successfully"
        elif command == "list":
            cmd = ["docker", "network", "ls"]
            result = subprocess.run(cmd, capture_output=True, text=True, check=True)
            return result.stdout
        else:
            return "Error: Invalid network command. Use 'create' with network name or 'list'"

    def _manage_volume(self, command: str, volume: Optional[str] = None) -> str:
        """Manage Docker volumes."""
        if command == "create" and volume:
            cmd = ["docker", "volume", "create", volume]
            result = subprocess.run(cmd, capture_output=True, text=True, check=True)
            return f"Volume '{volume}' created successfully"
        elif command == "list":
            cmd = ["docker", "volume", "ls"]
            result = subprocess.run(cmd, capture_output=True, text=True, check=True)
            return result.stdout
        else:
            return "Error: Invalid volume command. Use 'create' with volume name or 'list'"

    def _manage_compose(self, command: str, compose_file: str, service: Optional[str] = None) -> str:
        """Manage Docker Compose operations."""
        if not compose_file:
            return "Error: A 'compose_file' path is required for compose actions."

        if command == "up":
            cmd = ["docker", "compose", "-f", compose_file, "up", "-d"]
            result = subprocess.run(cmd, capture_output=True, text=True, check=True)
            return f"Docker Compose up for '{compose_file}' executed successfully.\n{result.stdout}"
        elif command == "down":
            cmd = ["docker", "compose", "-f", compose_file, "down"]
            result = subprocess.run(cmd, capture_output=True, text=True, check=True)
            return f"Docker Compose down for '{compose_file}' executed successfully.\n{result.stdout}"
        elif command == "exec":
            if not service:
                return "Error: 'service' name is required for compose exec action."
            cmd = ["docker", "compose", "-f", compose_file, "exec", service, "sh", "-c", self.command]
            result = subprocess.run(cmd, capture_output=True, text=True, check=True)
            return result.stdout
        else:
            return "Error: Invalid compose command. Use 'up', 'down', or 'exec'."


# Instantiate the enhanced tool
docker_tool = DockerTool()
