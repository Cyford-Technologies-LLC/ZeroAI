# Path: crews/coding/agents.py

from crewai import Agent
from crewai.tools import BaseTool
from distributed_router import DistributedRouter

def create_coding_developer_agent(router: DistributedRouter, inputs: dict[str, any]) -> Agent:
    # Explicitly request 'codellama:13b' from the router.
    coding_llm = router.get_llm_for_model('codellama:13b')
    if not coding_llm:
        # Fallback to a general model if the specific coding model is unavailable.
        coding_llm = router.get_llm_for_model('llama3.1:8b')
        if not coding_llm:
            raise ValueError("Failed to get LLM for coding developer agent.")

    return Agent(
        role='Senior Software Developer',
        goal=f'Write clean, efficient, and well-documented code for the task: "{inputs.get("topic")}".',
        backstory='A seasoned developer with expertise in multiple programming languages.',
        verbose=True,
        llm=coding_llm,
    )

def create_qa_engineer_agent(router: DistributedRouter, inputs: dict[str, any]) -> Agent:
    # Use a general-purpose model for QA tasks.
    qa_llm = router.get_llm_for_model('llama3.1:8b')
    if not qa_llm:
        raise ValueError("Failed to get LLM for QA engineer agent.")

    return Agent(
        role='Quality Assurance Engineer',
        goal='Review the generated code for correctness, bugs, and best practices.',
        backstory='A meticulous QA engineer who ensures all code is of the highest quality.',
        verbose=True,
        llm=qa_llm,
    )
