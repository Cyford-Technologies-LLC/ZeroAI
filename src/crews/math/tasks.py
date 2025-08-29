from crewai import Task, Agent
from typing import Dict, Any

def create_math_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    return Task(
        description=f"Solve the mathematical problem: {inputs.get('topic')}. Use the CalculatorTool to get the answer.",
        agent=agent,
        expected_output="The final numerical answer to the math problem."
    )
