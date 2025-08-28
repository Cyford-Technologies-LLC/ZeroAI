# Path: crews/coding/agents.py

from crewai import Agent
from config import config
from distributed_router import DistributedRouter # Import router
from langchain_community.llms.ollama import Ollama # If you use it for the Agent llm

def create_coding_developer_agent(router: DistributedRouter, inputs: dict) -> Agent:
    task_description = "Generate and refine coding solutions."
    llm = router.get_llm_for_task(task_description)

    return Agent(
        role='Senior Software Developer',
        goal='Develop and refine code based on specifications.',
        backstory=(
            "Experienced software developer specializing in efficient and scalable code. "
            "You are a master of clean and maintainable code."
        ),
        llm=llm,
        verbose=config.agents.verbose,
        allow_delegation=False
    )

def create_qa_engineer_agent(router: DistributedRouter, inputs: dict) -> Agent:
    task_description = "Write test cases for code."
    llm = router.get_llm_for_task(task_description)

    return Agent(
        role='Quality Assurance Engineer',
        goal='Ensure the quality and reliability of the code by writing comprehensive test cases.',
        backstory=(
            "A meticulous and detail-oriented QA engineer responsible for quality control. "
            "You write robust and effective tests to ensure code quality."
        ),
        llm=llm,
        verbose=config.agents.verbose,
        allow_delegation=False
    )
