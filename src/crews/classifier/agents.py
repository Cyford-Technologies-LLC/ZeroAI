import sys
from pathlib import Path
from typing import Optional, List, Tuple, Dict, Any
from rich.console import Console
from crewai import Agent, Task  # Import Task for use in the agent
from langchain_community.llms.ollama import Ollama
from config import config
from distributed_router import DistributedRouter

console = Console()


def create_classifier_agent(router: DistributedRouter) -> Agent:
    llm = router.get_llm_for_role('classifier')
    if not llm:
        # Fallback to local LLM, ensuring the router handles the prefix
        llm = router.get_local_llm("llama3.2:1b")
    if not llm:
        raise ValueError("Failed to get LLM for classifier agent.")
    console.print(
        f"🔗 Classifier Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    # Define a task for the classifier agent with few-shot examples
    # The agent will execute this task description to perform its classification
    classifier_task = Task(
        description=f"""
        Classify the following user inquiry into one of these categories: 'math', 'coding', 'research', or 'general'.

        Examples:
        Inquiry: How do I get the square root of a number in Python?
        Category: coding

        Inquiry: Make me a plan for a trip to Paris.
        Category: research

        Inquiry: What is the capital of France?
        Category: research

        Inquiry: What is 123 divided by 45?
        Category: math

        Inquiry: make a php function.
        Category: coding

        Provide ONLY the single word category name as your final output, do not include any other text or formatting.
        """,
        agent='Task Classifier',  # Use the agent's role as the assigned agent
        expected_output="A single word category from: 'math', 'coding', 'research', or 'general'."
    )

    return Agent(
        role='Task Classifier',
        goal='Accurately classify the user query into categories: math, coding, research, or general.',
        backstory=(
            "As a Task Classifier, the primary role is to analyze the incoming user query "
            "and determine the most suitable crew to handle it. Accuracy is critical "
            "to ensure the correct crew is activated for the job."
        ),
        llm=llm,
        verbose=config.agents.verbose,
        allow_delegation=False,
        # Assign the task directly to the agent's tasks, or handle it as part of the crew definition
        # Note: If the task is defined in the crew, this isn't necessary here.
    )

