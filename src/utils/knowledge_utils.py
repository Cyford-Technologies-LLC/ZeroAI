# src/utils/knowledge_utils.py
import os
import yaml
from typing import List, Dict, Any


def get_yaml_content(project_location: str, filename: str) -> str:
    """
    Reads a YAML file from the knowledge directory and returns its content as a string.

    Args:
        project_location: The sub-directory for the specific project.
        filename: The name of the YAML file to read.

    Returns:
        The content of the YAML file, or an error message if not found/readable.
    """
    file_path = os.path.join(
        "knowledge", "internal_crew", project_location, filename
    )

    if os.path.exists(file_path):
        try:
            with open(file_path, "r") as f:
                return f.read()
        except Exception as e:
            return f"Error reading YAML file at {file_path}: {e}"
    else:
        return f"YAML file not found at {file_path}"


def get_common_knowledge_strings(project_location: str, repository: str) -> List[str]:
    """
    Returns a list of content strings for the agent's knowledge.
    """
    knowledge_strings = []

    # Get YAML content
    yaml_content = get_yaml_content(project_location, "project_config.yaml")
    knowledge_strings.append(yaml_content)

    # Get repository info
    repo_string = f"The project's Git repository is located at: {repository}"
    knowledge_strings.append(repo_string)

    return knowledge_strings
