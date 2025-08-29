# src/crews/documentation/agents.py
from crewai import Agent
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from tools.file_tool import file_tool

def create_writer_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    task_description = "Generate or update documentation based on project changes."
    llm = router.get_llm_for_task(task_description)
    return Agent(
        role="Documentation Writer",
        goal="Create clear and concise documentation for software projects.",
        backstory="A skilled technical writer who translates complex code into understandable documentation.",
        llm=llm,
        tools=[file_tool],
        verbose=config.agents.verbose,
        allow_delegation=False
    )
