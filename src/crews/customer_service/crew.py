from crewai import Crew, Process, Agent # Import Agent
from typing import Dict, Any, List
from langchain_community.llms.ollama import Ollama
from config import config

from .agents import create_customer_service_agent
from .tasks import create_customer_service_task

def create_customer_service_crew(llm: Ollama, inputs: Dict[str, Any], specialist_agents: List[Agent]) -> Crew:
    customer_service_agent = create_customer_service_agent(llm, inputs)
    customer_service_task = create_customer_service_task(customer_service_agent, inputs)

    # Combine manager and specialist agents into a single list
    all_agents = [customer_service_agent] + specialist_agents

    return Crew(
        agents=all_agents,
        tasks=[customer_service_task],
        process=Process.hierarchical,
        manager_llm=llm,
        verbose=config.agents.verbose
    )
