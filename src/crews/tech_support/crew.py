# Path: crews/tech_support/crew.py

from crewai import Crew, Process
from typing import Dict, Any
from src.config import config
from .agents import create_tech_support_agent
from .tasks import create_tech_support_task
from distributed_router import DistributedRouter # Import router

# Add `full_output` to the function signature with a default value of False
def create_tech_support_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    tech_agent = create_tech_support_agent(router, inputs)
    tech_task = create_tech_support_task(tech_agent, inputs)

    return Crew(
        agents=[tech_agent],
        tasks=[tech_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output # Pass the argument to the Crew constructor
    )
