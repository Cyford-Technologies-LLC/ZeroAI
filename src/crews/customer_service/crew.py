from crewai import Crew, Process, Agent
from typing import Dict, Any, List
from config import config
from distributed_router import DistributedRouter

from .agents import create_customer_service_agent
from .tasks import create_customer_service_task

def create_customer_service_crew(router: DistributedRouter, inputs: Dict[str, Any], specialist_agents: List[Agent], full_output: bool = False) -> Crew:
    """Creates a customer service crew using the distributed router."""
    customer_service_agent = create_customer_service_agent(router, inputs)
    customer_service_task = create_customer_service_task(customer_service_agent, inputs)

    # Combine manager and specialist agents into a single list
    all_agents = [customer_service_agent] + specialist_agents

    return Crew(
        agents=all_agents,
        tasks=[customer_service_task],
        process=Process.hierarchical, # Hierarchical process
        manager_llm=router.get_llm_for_task("Manage hierarchical crew"),
        verbose=config.agents.verbose,
        full_output=full_output
    )

