from crewai import Task
from typing import Dict, Any

def create_customer_service_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    return Task(
        description=f"Process the following customer inquiry: {inputs.get('topic')}",
        agent=agent,
        expected_output="A polite and helpful response that addresses the customer's query. "
                        "If the query requires specialized knowledge, the response should "
                        "indicate that it is being delegated to the correct team."
    )
