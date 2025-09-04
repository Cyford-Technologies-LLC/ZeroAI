# Path: crews/tech_support/agents.py

from crewai import Agent
from src.config import config
from src.distributed_router import DistributedRouter # Import router
from langchain_community.llms.ollama import Ollama # If you use it for the Agent llm

def create_tech_support_agent(router: DistributedRouter, inputs: dict) -> Agent:
    task_description = "Provide technical support."
    llm = router.get_llm_for_task(task_description)

    return Agent(
        role='Tech Support Specialist',
        goal='Troubleshoot and resolve technical issues.',
        backstory=(
            "Expert in diagnosing and fixing hardware and software problems. "
            "You are patient, clear, and methodical in your approach."
        ),
        llm=llm,
        verbose=config.agents.verbose,
        allow_delegation=True
    )
