# src/crews/internal/research/crew.py

from crewai import Crew, Process
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from .agents import create_internal_researcher_agent, create_internal_analyst_agent
from .tasks import internal_research_task, internal_analysis_task

def create_research_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    researcher_agent = create_internal_researcher_agent(router, inputs)
    analyst_agent = create_internal_analyst_agent(router, inputs)

    tasks = [
        internal_research_task(researcher_agent, inputs),
        internal_analysis_task(analyst_agent, inputs)
    ]

    return Crew(
        agents=[researcher_agent, analyst_agent],
        tasks=tasks,
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )
