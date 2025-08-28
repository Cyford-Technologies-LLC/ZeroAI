from crewai import Crew, Process
from typing import Dict, Any
from langchain_community.llms.ollama import Ollama
from config import config
from .agents import create_mathematician_agent
from .tasks import create_math_task

# Add `full_output` to the function signature with a default value of False
def create_math_crew(llm: Ollama, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    mathematician = create_mathematician_agent(llm, inputs)
    math_task = create_math_task(mathematician, inputs)

    return Crew(
        agents=[mathematician],
        tasks=[math_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output # Pass the argument to the Crew constructor
    )
