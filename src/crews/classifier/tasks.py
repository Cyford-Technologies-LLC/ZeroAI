# src/crews/customer_service/tasks.py

from crewai import Task, Agent
from typing import Dict, Any

def create_customer_service_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    return Task(
        description=f"""
        Analyze the customer inquiry: {inputs.get('topic')}.
        Identify which specialist agent is best suited to handle this inquiry (e.g., math, tech support, coding, etc.).
        Delegate the inquiry to the most appropriate specialist agent.

        **CRITICAL:** If any delegation fails or a specialist agent cannot provide a satisfactory response, you **must** fall back to providing a simple, direct answer to the customer yourself.
        """,
        agent=agent,
        expected_output="The final, polished answer to the customer's query, either from a specialist or from the customer service agent as a fallback."
    )
