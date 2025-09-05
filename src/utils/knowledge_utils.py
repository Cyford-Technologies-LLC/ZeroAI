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


def get_common_knowledge(project_location: str, repository: str) -> List[Dict[str, Any]]:
    knowledge_sources = []

    # 1. Read the YAML file content and prepare for the Agent
    yaml_content = get_yaml_content(project_location, "project_config.yaml")
    if not yaml_content.startswith("Error"):
        knowledge_sources.append({"BaseKnowledgeSource": yaml_content})

    # 2. Prepare the repository variable
    repo_content = f"The project's Git repository is located at: {repository}"
    knowledge_sources.append({"BaseKnowledgeSource": repo_content})

    # Sanitize the output to ensure only strings are present in content
    sanitized_knowledge = []
    for item in knowledge_sources:
        if isinstance(item, dict) and 'content' in item and isinstance(item['content'], str):
            sanitized_knowledge.append(item)

    return sanitized_knowledge
