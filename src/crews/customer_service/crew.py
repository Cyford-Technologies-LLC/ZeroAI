from crewai import Crew, Process
from typing import Dict, Any
from langchain_community.llms.ollama import Ollama
from config import config

from .agents import create_customer_service_agent
from .tasks import create_customer_service_task

def create_customer_service_crew(llm: Ollama, inputs: Dict[str, Any]) -> Crew:
    customer_service_agent = create_customer_service_agent(llm, inputs)
    customer_service_task = create_customer_service_task(customer_service_agent, inputs)

    return Crew(
        agents=[customer_service_agent],
        tasks=[customer_service_task],
        process=Process.sequential,
        verbose=config.agents.verbose
    )
