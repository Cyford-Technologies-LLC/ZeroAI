from crewai import Crew, Task
from typing import Dict, Any
from src.config import config
from src.distributed_router import DistributedRouter
from .agents import create_classifier_agent


def create_classifier_crew(router: DistributedRouter, inputs: Dict[str, Any]) -> Crew:
    # Pass inputs to the agent creation function
    classifier_agent = create_classifier_agent(router, inputs)
    classifier_task = Task(
        description=f"""
        Classify the following user inquiry into one of these categories: 'math', 'coding', 'research', or 'general'.

        Examples:
        Inquiry: How do I get the square root of a number in Python?
        Category: coding

        Inquiry: What is the capital of France?
        Category: research

        Inquiry: What is 123 divided by 45?
        Category: math

        Inquiry: What is the meaning of life?
        Category: general

        Inquiry: make a php function.
        Category: coding

        Inquiry: {inputs.get('topic')}.

        Provide ONLY the single word category name as your final output, do not include any other text or formatting.
        """,
        agent=classifier_agent,
        expected_output="A single word representing the category: math, coding, research, or general.",
    )
    return Crew(
        agents=[classifier_agent],
        tasks=[classifier_task],
        verbose=config.agents.verbose,
        full_output=True
    )
