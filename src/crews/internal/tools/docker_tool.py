# src/crews/internal/tools/docker_tool.py

from crewai.tools import BaseTool
import subprocess
import time
from typing import Optional, Any
from pydantic import BaseModel, Field
import logging
import os


class DockerToolSchema(BaseModel):
    action: str = Field(
        description="Action: 'run', 'exec', 'stop', 'remove', 'list', 'logs', 'network', 'volume', 'compose_up', 'compose_down'")
    image: Optional[str] = Field(None, description="Docker image name (for run action)")
    container: Optional[str] = Field(None, description="Container name or ID")
    command: Optional[str] = Field(None, description="Command to execute")
    ports: Optional[str] = Field(None, description="Port mapping (e.g., '8080:80')")
    volumes: Optional[str] = Field(None, description="Volume mapping (e.g., '/host:/container')")
    environment: Optional[str] = Field(None, description="Environment variables (e.g., 'KEY=value')")
    network: Optional[str] = Field(None, description="Docker network name")
    detach: bool = Field(True, description="Run in detached mode")
    compose_file: Optional[str] = Field(None, description="Path to the Docker-compose.yml file")
    compose_services: Optional[str] = Field(None,
                                            description="Comma-separated list of services to target in docker-compose command")


class DockerTool(BaseTool):
    name: str = "Docker Operator Tool"
    description: str = "Enhanced Docker tool for container management, networking, and orchestration, including Docker Compose functionality."
    args_schema: type[BaseModel] = DockerToolSchema

    def _run(self, action: str, image: Optional[str] = None, container: Optional[str] = None,
             command: Optional[str] = None, ports: Optional[str] = None, volumes: Optional[str] = None,
             environment: Optional[str] = None, network: Optional[str] = None, detach: bool = True,
             compose_file: Optional[str] = None, compose_services: Optional[str] = None) -> str:
        """
        Enhanced Docker operations with container management capabilities, including Docker Compose.
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
            elif action == "compose_up":
                return self._compose_up(compose_file, compose_services)
            elif action == "compose_down":
                return self._compose_down(compose_file, compose_services)
            else:
                return f"Unknown action: {action}. Available: run, exec, stop, remove, list, logs, network, volume, compose_up, compose_down"
        except Exception as e:
            logging.error(f"Docker operation failed: {str(e)}")
            return f"Error: {str(e)}"

    def _execute_command(self, cmd: list, working_dir: Optional[str] = None) -> str:
        """
        Helper function to execute a shell command and handle potential errors.
        """
        try:
            result = subprocess.run(cmd, capture_output=True, text=True, check=True, cwd=working_dir)
            return result.stdout.strip()
        except subprocess.CalledProcessError as e:
            error_message = f"Command failed with return code {e.returncode}. Stderr: {e.stderr.strip()}"
            logging.error(error_message)
            raise Exception(error_message)

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

        container_name = f"zeroai-{image.replace(':', '-').replace('/', '-')}-{int(time.time())}"
        cmd.extend(["--name", container_name])
        cmd.append(image)
        if command:
            cmd.extend(command.split())

        return self._execute_command(cmd)

    def _exec_container(self, container: str, command: str) -> str:
        """Execute command in running container."""
        if not container or not command:
            return "Error: Container name and command are required for exec action"
        cmd = ["docker", "exec", container, "sh", "-c", command]
        return self._execute_command(cmd)

    def _stop_container(self, container: str) -> str:
        """Stop running container."""
        if not container:
            return "Error: Container name is required for stop action"
        cmd = ["docker", "stop", container]
        return self._execute_command(cmd)

    def _remove_container(self, container: str) -> str:
        """Remove container."""
        if not container:
            return "Error: Container name is required for remove action"
        cmd = ["docker", "rm", "-f", container]
        return self._execute_command(cmd)

    def _list_containers(self) -> str:
        """List all containers."""
        cmd = ["docker", "ps", "-a", "--format", "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}"]
        return self._execute_command(cmd)

    def _get_logs(self, container: str) -> str:
        """Get container logs."""
        if not container:
            return "Error: Container name is required for logs action"
        cmd = ["docker", "logs", "--tail", "50", container]
        return self._execute_command(cmd)

    def _manage_network(self, command: str, network: Optional[str] = None) -> str:
        """Manage Docker networks."""
        if command == "create" and network:
            cmd = ["docker", "network", "create", network]
            return self._execute_command(cmd)
        elif command == "list":
            cmd = ["docker", "network", "ls"]
            return self._execute_command(cmd)
        else:
            return "Error: Invalid network command. Use 'create' with network name or 'list'"

    def _manage_volume(self, command: str, volume: Optional[str] = None) -> str:
        """Manage Docker volumes."""
        if command == "create" and volume:
            cmd = ["docker", "volume", "create", volume]
            return self._execute_command(cmd)
        elif command == "list":
            cmd = ["docker", "volume", "ls"]
            return self._execute_command(cmd)
        else:
            return "Error: Invalid volume command. Use 'create' with volume name or 'list'"

    def _compose_up(self, compose_file: Optional[str] = None, compose_services: Optional[str] = None) -> str:
        """
        Start services from a Docker-compose.yml file.
        """
        if not compose_file:
            raise ValueError("A compose_file path is required for the compose_up action.")

        working_dir = os.path.dirname(os.path.abspath(compose_file))
        compose_filename = os.path.basename(compose_file)

        # Determine which compose command to use (docker compose vs docker-compose)
        compose_command = ["docker", "compose"]
        try:
            # Check if 'docker compose' is valid
            subprocess.run(compose_command, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        except (subprocess.CalledProcessError, FileNotFoundError):
            compose_command = ["docker-compose"]  # Fallback if not found

        cmd = compose_command + ["-f", compose_filename, "up", "-d"]  # Run in detached mode

        if compose_services:
            cmd.extend(compose_services.split(','))

        return self._execute_command(cmd, working_dir=working_dir)

    def _compose_down(self, compose_file: Optional[str] = None, compose_services: Optional[str] = None) -> str:
        """
        Stop and remove services from a Docker-compose.yml file.
        """
        if not compose_file:
            raise ValueError("A compose_file path is required for the compose_down action.")

        working_dir = os.path.dirname(os.path.abspath(compose_file))
        compose_filename = os.path.basename(compose_file)

        # Determine which compose command to use (docker compose vs docker-compose)
        compose_command = ["docker", "compose"]
        try:
            subprocess.run(compose_command, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        except (subprocess.CalledProcessError, FileNotFoundError):
            compose_command = ["docker-compose"]  # Fallback if not found

        cmd = compose_command + ["-f", compose_filename, "down"]

        if compose_services:
            cmd.extend(compose_services.split(','))

        return self._execute_command(cmd, working_dir=working_dir)

# Instantiate the enhanced tool
docker_tool = DockerTool()
