# src/utils/knowledge_utils.py
import os
import yaml
from typing import List, Dict, Any
from crewai.knowledge.source.string_knowledge_source import StringKnowledgeSource


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


def get_common_knowledge(project_location: str, repository: str) -> List[StringKnowledgeSource]:
    knowledge_sources = []

    # 1. Read the YAML file content and create a StringKnowledgeSource
    yaml_file_path = os.path.join(
        "knowledge", "internal_crew", project_location, "project_config.yaml"
    )
    if os.path.exists(yaml_file_path):
        try:
            with open(yaml_file_path, "r") as f:
                yaml_content = f.read()
            knowledge_sources.append(StringKnowledgeSource(content=yaml_content))
        except Exception as e:
            print(f"Error reading YAML file at {yaml_file_path}: {e}")
    else:
        print(f"YAML file not found at {yaml_file_path}")

    # 2. Create a StringKnowledgeSource for the repository variable
    repo_knowledge = StringKnowledgeSource(
        content=f"The project's Git repository is located at: {repository}"
    )
    knowledge_sources.append(repo_knowledge)

    return knowledge_sources
