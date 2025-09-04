# src/crews/internal/documentation/crew.py
from crewai import Crew, Process
from typing import Dict, Any, List
from src.distributed_router import DistributedRouter
from src.config import config

from src.crews.internal.documentation.agents import create_writer_agent
from .tasks import update_docs_task

def get_documentation_crew(router, tools, project_config, use_new_memory=False):
    """
    Wrapper function to create the documentation crew.
    """
    inputs = {
        "working_dir": project_config.get("crewai_settings", {}).get("working_directory", "/tmp"),
        "project_name": project_config.get("project", {}).get("name", "unknown"),
        "tools": tools, # Pass the tools here
    }
    return create_documentation_crew(router, inputs, full_output=True)

def create_documentation_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    """Creates a documentation crew."""
    writer_agent = create_writer_agent(router, inputs)
    writer_task = update_docs_task(writer_agent, inputs)

    return Crew(
        agents=[writer_agent],
        tasks=[writer_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )
