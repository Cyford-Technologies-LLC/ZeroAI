from crewai import Crew, Process, Agent
from typing import Dict, Any, List
from src.config import config
from distributed_router import DistributedRouter

from .agents import create_customer_service_agent
from .tasks import create_customer_service_task

# crews/customer_service/crew.py

# Remove the specialist_agents parameter
def create_customer_service_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    """Creates a customer service crew using the distributed router."""
    customer_service_agent = create_customer_service_agent(router, inputs)
    customer_service_task = create_customer_service_task(customer_service_agent, inputs)

    return Crew(
        agents=[customer_service_agent], # Only the customer service agent is needed
        tasks=[customer_service_task],
        process=Process.sequential, # Change to sequential process
        verbose=config.agents.verbose,
        full_output=full_output
    )


