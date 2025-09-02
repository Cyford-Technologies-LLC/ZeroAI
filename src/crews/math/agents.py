from crewai import Agent
from crewai.tools import BaseTool
from .tools import calculator_tool# crews/math/agents.py

from crewai import Agent
from langchain_community.llms.ollama import Ollama
# Assuming your config is accessible here
from src.config import config
# Assuming you have an updated router and a way to get it
from distributed_router import DistributedRouter

def create_mathematician_agent(router: DistributedRouter, inputs: dict) -> Agent:
    task_description = "Solve mathematical problems."
    # Use router to get a specialized LLM for math
    llm = router.get_llm_for_task(task_description)

    return Agent(
        role='Mathematician',
        goal='Accurately and efficiently solve mathematical problems.',
        backstory=(
            "A seasoned mathematician with a reputation for precision and speed. "
            "You are an expert at breaking down complex equations and delivering clear, "
            "accurate solutions."
        ),
        llm=llm,
        verbose=config.agents.verbose,
        allow_delegation=False
    )
