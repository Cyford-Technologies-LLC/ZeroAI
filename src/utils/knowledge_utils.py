import os
import yaml
from typing import List, Tuple
from crewai.knowledge.source.string_knowledge_source import StringKnowledgeSource
from crewai.utilities import OllamaEmbedder


def get_yaml_content(project_location: str, filename: str) -> str:
    # ... (your existing function)
    pass


def get_common_knowledge_and_embedder(project_location: str, repository: str) -> Tuple[
    List[StringKnowledgeSource], OllamaEmbedder]:
    """
    Retrieves knowledge sources and creates the OllamaEmbedder.

    Args:
        project_location: The sub-directory for the project's knowledge base.
        repository: The URL of the project's Git repository.

    Returns:
        A tuple containing a list of StringKnowledgeSource instances and the OllamaEmbedder instance.
    """
    knowledge_sources = []

    # 1. Read the YAML file content and wrap it in a StringKnowledgeSource
    yaml_content = get_yaml_content(project_location, "project_config.yaml")
    if not yaml_content.startswith("Error"):
        yaml_source = StringKnowledgeSource(
            title="Project Configuration",
            content=yaml_content
        )
        knowledge_sources.append(yaml_source)

    # 2. Prepare the repository information and wrap it in a StringKnowledgeSource
    repo_content = f"The project's Git repository is located at: {repository}"
    repo_source = StringKnowledgeSource(
        title="Project Repository",
        content=repo_content
    )
    knowledge_sources.append(repo_source)

    # 3. Define the OllamaEmbedder instance
    ollama_embedder = OllamaEmbedder(
        model="nomic-embed-text",
        base_url="http://149.36.1.65:11434"
    )

    return knowledge_sources, ollama_embedder


# --- Usage Example ---
from crewai import Crew, Agent

# Get both the knowledge sources and the embedder from the utility function
common_knowledge, ollama_embedder = get_common_knowledge_and_embedder(
    project_location="cyford/zeroai",
    repository="https://github.com/Cyford-Technologies-LCC/ZeroAI.git"
)

# Now, use them to create the crew and its agents
master_crew = Crew(
    agents=[
        Agent(..., knowledge_sources=common_knowledge),
        Agent(..., knowledge_sources=common_knowledge)
    ],
    tasks=[...],
    embedder=ollama_embedder
)
