import os
import yaml
from typing import List, Tuple
from crewai.knowledge.source.string_knowledge_source import StringKnowledgeSource
from langchain_ollama import OllamaEmbeddings

# Define the Ollama embedder and point to your local endpoint.
ollama_embedder = OllamaEmbeddings(
    model="nomic-embed-text",
    base_url="http://149.36.1.65:11434"
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


# --- Usage Example with the embedder passed to the main Crew ---
from crewai import Crew, Agent
from crewai_tools import GitTool

# Make sure to pull the `nomic-embed-text` embeddings model with: `ollama pull nomic-embed-text`

# Get the common knowledge sources
common_knowledge = get_common_knowledge(
    project_location="cyford/zeroai",
    repository="https://github.com/Cyford-Technologies-LLC/ZeroAI.git"
)

# Define the LLM model
llm_model = "ollama/mistral-nemo:latest"

# Create agents, passing the common_knowledge
online_researcher_agent = Agent(
    role="Online Researcher",
    goal="Gather relevant information on a given topic.",
    backstory="...",
    llm=llm_model,
    tools=[GitTool()],  # Add any necessary tools
    knowledge_sources=common_knowledge,
)

diagnostic_agent = Agent(
    role="Diagnostic Agent",
    goal="Diagnose and explain errors.",
    backstory="...",
    llm=llm_model,
    tools=[GitTool()],  # Add any necessary tools
    knowledge_sources=common_knowledge,
)

# Create the crew and set the `embedder`
master_crew = Crew(
    agents=[online_researcher_agent, diagnostic_agent],
    tasks=[],  # Add your tasks here
    embedder=ollama_embedder,  # Set the embedder for the entire crew
    # Set the distributed router process if using CrewAI Flows
)

# Now, run your crew
# result = master_crew.kickoff()
