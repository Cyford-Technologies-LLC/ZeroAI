from crewai import Crew, Process
from typing import Dict, Any
from langchain_community.llms.ollama import Ollama
from config import config

from .agents import create_coding_developer_agent, create_qa_engineer_agent
from .tasks import create_coding_task, create_review_task

def create_coding_crew(llm: Ollama, inputs: Dict[str, Any]) -> Crew:
    coder = create_coding_developer_agent(llm, inputs)
    qa_engineer = create_qa_engineer_agent(llm, inputs)
    coding_task = create_coding_task(coder, inputs)
    review_task = create_review_task(qa_engineer, inputs)

    return Crew(
        agents=[coder, qa_engineer],
        tasks=[coding_task, review_task],
        verbose=config.agents.verbose,
        full_output = full_output  # Pass the argument to the Crew constructor
    )
