# src/crews/developer/agents.py

from crewai import Agent
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from tools.file_tool import file_tool
from rich.console import Console

console = Console()


def create_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    task_description = "Analyze bug reports, code, and project context."
    llm = None
    try:
        # Attempt to get LLM from the distributed router based on task
        llm = router.get_llm_for_task(task_description)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for researcher via router: {e}", style="yellow")
        # FIX: Fallback to a specified local model if routing fails
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError("Failed to get LLM for researcher agent after all attempts.")

    console.print(
        f"🔗 Researcher Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    return Agent(
        role="Code Researcher",
        goal="Understand and analyze bug reports to find the root cause.",
        backstory="An expert in software analysis, specializing in finding code issues.",
        llm=llm,
        tools=[file_tool],
        verbose=config.agents.verbose,
        allow_delegation=False
    )


def create_coder_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    task_description = "Write and apply code changes to fix bugs."
    llm = None
    try:
        # Attempt to get LLM from the distributed router based on task
        llm = router.get_llm_for_task(task_description)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for coder via router: {e}", style="yellow")
        # FIX: Fallback to a specified local model if routing fails
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError("Failed to get LLM for coder agent after all attempts.")

    console.print(
        f"🔗 Senior Developer Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    return Agent(
        role="Senior Developer",
        goal="Implement bug fixes and write clean, maintainable code.",
        backstory="A seasoned developer with a knack for solving complex coding problems.",
        llm=llm,
        tools=[file_tool],
        verbose=config.agents.verbose,
        allow_delegation=False
    )


def create_tester_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    task_description = "Write and run tests to verify code fixes."
    llm = None
    try:
        # Attempt to get LLM from the distributed router based on task
        llm = router.get_llm_for_task(task_description)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for tester via router: {e}", style="yellow")
        # FIX: Fallback to a specified local model if routing fails
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError("Failed to get LLM for tester agent after all attempts.")

    console.print(
        f"🔗 QA Engineer Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    return Agent(
        role="QA Engineer",
        goal="Ensure all bug fixes are verified with comprehensive tests.",
        backstory="A meticulous QA engineer who ensures code quality and correctness.",
        llm=llm,
        tools=[file_tool],
        verbose=config.agents.verbose,
        allow_delegation=False
    )
