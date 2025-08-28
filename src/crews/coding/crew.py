# Path: crews/coding/crew.py

from crewai import Crew, Process
from typing import Dict, Any
from config import config
from .agents import create_coding_developer_agent, create_qa_engineer_agent
from .tasks import create_coding_task
from distributed_router import DistributedRouter # Import router

def create_coding_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    # Pass the router instance to the agent creation functions
    coding_developer = create_coding_developer_agent(router, inputs)
    qa_engineer = create_qa_engineer_agent(router, inputs)
    coding_task = create_coding_task(coding_developer, inputs)

    return Crew(
        agents=[coding_developer, qa_engineer],
        tasks=[coding_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )
