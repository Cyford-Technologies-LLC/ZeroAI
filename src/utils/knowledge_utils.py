# src/utils/knowledge_utils.py
import os
import yaml
# Check this import path after reinstalling
from crewai.knowledge.source.string_knowledge_source import StringKnowledgeSource
from typing import List

def get_common_knowledge(project_location: str, repository: str) -> List[StringKnowledgeSource]:
    """
    Loads common knowledge sources for agents, including project config and repository info.

    Args:
        project_location: The sub-directory for the specific project.
        repository: The Git repository URL.

    Returns:
        A list of StringKnowledgeSource objects.
    """
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

