from crewai import Task, Agent
from typing import Dict, Any

def create_tech_support_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    return Task(
        description=f"Resolve the technical issue: {inputs.get('topic')}. Context: {inputs.get('context')}.",
        expected_output="A detailed solution or resolution steps for the technical problem.",
        agent=agent,
    )
