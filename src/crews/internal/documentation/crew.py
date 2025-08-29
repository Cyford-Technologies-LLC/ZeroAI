from crewai import Crew, Process
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config

from .agents import create_writer_agent
from .tasks import update_docs_task


def create_documentation_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    writer_agent = create_writer_agent(router, inputs)
    writer_task = update_docs_task(writer_agent, inputs)

    return Crew(
        agents=[writer_agent],
        tasks=[writer_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )
