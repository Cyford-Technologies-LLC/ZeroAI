import sys
from pathlib import Path
from typing import Optional, List, Tuple, Dict, Any
from rich.console import Console
from crewai import Agent, Task
from langchain_community.llms.ollama import Ollama
from config import config
from distributed_router import DistributedRouter

console = Console()


def create_classifier_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    """Creates a classifier agent with dynamic LLM selection."""
    llm = None
    try:
        # Use the dynamic router to select the optimal LLM for the classifier's prompt
        # The prompt is the user's topic, passed from the inputs
        llm = router.get_llm_for_task(inputs.get('topic'))
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for classifier via router: {e}", style="yellow")
        # Fallback to local LLM if routing fails
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError("Failed to get LLM for classifier agent.")

    console.print(
        f"🔗 Classifier Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    # The task definition for the classifier, including few-shot examples
    # The task itself will be created in the crew function, but defined here for clarity

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
    )
