from crewai import Crew, Process
from typing import Dict, Any, List
from langchain_community.llms.ollama import Ollama
from config import config

from .agents import create_customer_service_agent, math_delegation_tool, research_delegation_tool, tech_support_tool
from .tasks import create_customer_service_task

def create_customer_service_crew(llm: Ollama, inputs: Dict[str, Any], specialist_agents: List[Agent]) -> Crew:
    customer_service_agent = create_customer_service_agent(llm, inputs)
    customer_service_task = create_customer_service_task(customer_service_agent, inputs)

    return Crew(
        agents=[customer_service_agent] + specialist_agents,
        tasks=[customer_service_task],
        process=Process.hierarchical,
        manager_llm=llm,
        verbose=config.agents.verbose
    )
