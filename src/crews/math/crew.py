# crews/math/crew.py

from crewai import Crew, Process
from typing import Dict, Any
from langchain_community.llms.ollama import Ollama
from src.config import config
from .agents import create_mathematician_agent
from .tasks import create_math_task
from src.distributed_router import DistributedRouter # Import router

def create_math_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    # Pass the router, not the llm instance
    mathematician = create_mathematician_agent(router, inputs)
    math_task = create_math_task(mathematician, inputs)

    return Crew(
        agents=[mathematician],
        tasks=[math_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output # Pass the argument to the Crew constructor
    )
