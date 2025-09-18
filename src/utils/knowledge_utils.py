import os
import yaml
from typing import List, Tuple
from crewai import Agent, Task, Crew, Process, LLM
from crewai.knowledge.source.string_knowledge_source import     StringKnowledgeSource
from crewai.knowledge.source.base_knowledge_source import BaseKnowledgeSource

from langchain_ollama import OllamaEmbeddings
from langchain_ollama import OllamaLLM
#from langchain_community.embeddings import OllamaEmbeddings


from ollama import Client as OllamaClient # Import the client directly




def get_ollama_client(base_url: str) -> OllamaClient:
    """Creates a configured Ollama client, explicitly passing the host."""
    # The Ollama client expects the host without the protocol prefix in some contexts,
    # so we'll pass the full URL and let the client parse it.
    return OllamaClient(host=base_url)

# os.environ['OLLAMA_HOST'] = "http:// gpu-001:11434"
# Define the Ollama embedder and point to your local endpoint.
ollama_embedder = OllamaEmbeddings(
    model="mxbai-embed-large",
    base_url="http:// gpu-001:11434"
)


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
    """
    Retrieves and prepares knowledge sources for a CrewAI agent.

    This version correctly formats knowledge sources as StringKnowledgeSource instances.

    Args:
        project_location: The sub-directory for the project's knowledge base.
        repository: The URL of the project's Git repository.

    Returns:
        A list of StringKnowledgeSource instances.
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

    return knowledge_sources