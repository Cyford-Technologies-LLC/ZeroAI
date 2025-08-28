from crewai import Crew, Process
from typing import Dict, Any
from langchain_community.llms.ollama import Ollama
from config import config

from .agents import create_customer_service_agent, create_math_delegation_tool, create_research_delegation_tool, create_technical_support_tool
from .tasks import create_customer_service_task

# Import other crew creation functions here
from crews.math.crew import create_math_crew
from crews.research.crew import create_research_crew

def create_customer_service_crew(llm: Ollama, inputs: Dict[str, Any]) -> Crew:
    customer_service_agent = create_customer_service_agent(llm, inputs)
    customer_service_task = create_customer_service_task(customer_service_agent, inputs)

    # Create the specialist crews
    math_crew = create_math_crew(llm, inputs)
    research_crew = create_research_crew(llm, inputs)

    return Crew(
        agents=[customer_service_agent],
        tasks=[customer_service_task],
        process=Process.hierarchical,
        manager_llm=llm,
        verbose=config.agents.verbose
    )
