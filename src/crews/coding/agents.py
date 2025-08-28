# Path: crews/coding/agents.py

from crewai import Agent
from crewai.tools import BaseTool
from distributed_router import DistributedRouter # Import the router

def create_coding_developer_agent(router: DistributedRouter, inputs: dict[str, any]) -> Agent:
    # Get the LLM specifically for the developer role from the router
    llm = router.get_llm_for_role('coding developer')
    if not llm:
        raise ValueError("Failed to get LLM for coding developer agent.")

    return Agent(
        role='Senior Software Developer',
        goal=f'Write clean, efficient, and well-documented code for the task: "{inputs.get("topic")}".',
        backstory='A seasoned developer with expertise in multiple programming languages.',
        verbose=True,
        llm=llm, # Assign the dynamically selected LLM
    )

def create_qa_engineer_agent(router: DistributedRouter, inputs: dict[str, any]) -> Agent:
    # Get the LLM specifically for the QA role from the router
    llm = router.get_llm_for_role('qa engineer')
    if not llm:
        raise ValueError("Failed to get LLM for QA engineer agent.")

    return Agent(
        role='Quality Assurance Engineer',
        goal='Review the generated code for correctness, bugs, and best practices.',
        backstory='A meticulous QA engineer who ensures all code is of the highest quality.',
        verbose=True,
        llm=llm, # Assign the dynamically selected LLM
    )
