from crewai import Crew, Process
from typing import Dict, Any
from langchain_community.llms.ollama import Ollama
from config import config
from .agents import create_mathematician_agent
from .tasks import create_math_task

def create_math_crew(llm: Ollama, inputs: Dict[str, Any]) -> Crew:
    mathematician = create_mathematician_agent(llm, inputs)
    math_task = create_math_task(mathematician, inputs)

    return Crew(
        agents=[mathematician],
        tasks=[math_task],
        process=Process.sequential,
        verbose=config.agents.verbose
    )
