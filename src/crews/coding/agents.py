from crewai import Agent
from crewai.tools import BaseTool

def create_coding_developer_agent(llm, inputs: dict[str, any]) -> Agent:
    return Agent(
        role='Senior Software Developer',
        goal=f'Write clean, efficient, and well-documented code for the task: "{inputs.get("topic")}".',
        backstory='A seasoned developer with expertise in multiple programming languages.',
        verbose=True,
        llm=llm,
    )

def create_qa_engineer_agent(llm, inputs: dict[str, any]) -> Agent:
    return Agent(
        role='Quality Assurance Engineer',
        goal='Review the generated code for correctness, bugs, and best practices.',
        backstory='A meticulous QA engineer who ensures all code is of the highest quality.',
        verbose=True,
        llm=llm,
    )
