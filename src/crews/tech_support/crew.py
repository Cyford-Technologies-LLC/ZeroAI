from crewai import Crew, Process
from typing import Dict, Any
from langchain_community.llms.ollama import Ollama
from config import config
from .agents import create_tech_support_agent
from .tasks import create_tech_support_task

# Add `full_output` to the function signature with a default value of False
def create_tech_support_crew(llm: Ollama, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    tech_agent = create_tech_support_agent(llm, inputs)
    tech_task = create_tech_support_task(tech_agent, inputs)

    return Crew(
        agents=[tech_agent],
        tasks=[tech_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output # Pass the argument to the Crew constructor
    )
