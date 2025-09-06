# src/crews/internal/research/crew.py
from crewai import Crew, Process
from typing import Dict, Any, List
from src.distributed_router import DistributedRouter
from src.config import config
from src.crews.internal.research.agents  import (
    create_project_manager_agent,
    create_internal_researcher_agent,
    create_online_researcher_agent
)
from src.crews.internal.research.tasks import (
    internal_research_task,
    online_research_task,
    project_management_task
)

ollama_embedder_config = {
    "provider": "ollama",
    "config": {
        "model": "mxbai-embed-large",
        "base_url": "http://149.36.1.65:11434"
    }
}


def create_research_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    # Create agents with proper role assignments and pass the embedder config
    internal_researcher = create_internal_researcher_agent(router, inputs, ollama_embedder_config=ollama_embedder_config)
    online_researcher = create_online_researcher_agent(router, inputs, ollama_embedder_config=ollama_embedder_config)
    project_manager = create_project_manager_agent(router, inputs, ollama_embedder_config=ollama_embedder_config)

    # Create tasks specific to each agent's role
    tasks = [
        internal_research_task(internal_researcher, inputs),
        online_research_task(online_researcher, inputs),
        project_management_task(project_manager, inputs),
    ]

    return Crew(
        agents=[internal_researcher, online_researcher, project_manager],
        tasks=tasks,
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output,
        embedder=ollama_embedder_config # <-- Set the crew-level embedder
    )
