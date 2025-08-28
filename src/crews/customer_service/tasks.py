from crewai import Task, Agent
from typing import Dict, Any

def create_customer_service_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    return Task(
        description=f"Initial customer inquiry: {inputs.get('topic')}",
        agent=agent,
        expected_output="A polite response that addresses the customer's query. If the query requires specialized knowledge, the response should include the specialist's final answer."
    )

def create_delegation_task(specialist_agent: Agent, sub_task_description: str) -> Task:
    return Task(
        description=sub_task_description,
        agent=specialist_agent,
        expected_output="The result of the specialized sub-task."
    )
