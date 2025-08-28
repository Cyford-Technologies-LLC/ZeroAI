# Path: crews/coding/crew.py

from crewai import Crew, Process
from typing import Dict, Any
from langchain_community.llms.ollama import Ollama
from config import config
from .agents import create_coding_developer_agent, create_qa_engineer_agent
from .tasks import create_coding_task

# Add `full_output` to the function signature with a default value of False
def create_coding_crew(llm: Ollama, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    coding_developer = create_coding_developer_agent(llm, inputs)
    qa_engineer = create_qa_engineer_agent(llm, inputs)
    coding_task = create_coding_task(coding_developer, inputs)

    return Crew(
        agents=[coding_developer, qa_engineer],
        tasks=[coding_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output # Pass the argument to the Crew constructor
    )
