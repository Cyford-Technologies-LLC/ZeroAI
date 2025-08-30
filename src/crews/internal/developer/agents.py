# src/crews/internal/developer/agents.py

from crewai import Agent
from typing import Dict, Any, List
from distributed_router import DistributedRouter
from config import config
from tools.file_tool import file_tool
from rich.console import Console

console = Console()

def create_code_researcher(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    """Create a Code Researcher agent."""
    task_description = "Analyze bug reports, code, and project context."

    # Model preference order: codellama:13b -> llama3.1:8b -> llama3.2:latest -> llama3.2:1b
    preferred_models = ["codellama:13b", "llama3.1:8b", "llama3.2:latest", "llama3.2:1b"]

    # Try to get learning-based model preference
    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference("developer")
        if category_model:
            if category_model not in preferred_models:
                preferred_models.insert(0, category_model)
    except ImportError:
        pass  # Learning module not available

    llm = None
    try:
        # Use the updated get_llm_for_task with preferred models
        llm = router.get_llm_for_task(task_description, preferred_models)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for code researcher via router: {e}", style="yellow")
        # Fall back to local model
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError("Failed to get LLM for code researcher agent after all attempts.")

    console.print(
        f"🔗 Code Researcher Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
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

def create_senior_developer(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    """Create a Senior Developer agent."""
    task_description = "Write and apply code changes to fix bugs."

    # Model preference order: codellama:13b -> llama3.1:8b -> llama3.2:latest -> llama3.2:1b
    preferred_models = ["codellama:13b", "llama3.1:8b", "llama3.2:latest", "llama3.2:1b"]

    # Try to get learning-based model preference
    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference("developer")
        if category_model:
            if category_model not in preferred_models:
                preferred_models.insert(0, category_model)
    except ImportError:
        pass

    llm = None
    try:
        # Use the updated get_llm_for_task with preferred models
        llm = router.get_llm_for_task(task_description, preferred_models)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for senior developer via router: {e}", style="yellow")
        # Fall back to local model
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError("Failed to get LLM for senior developer agent after all attempts.")

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

def create_qa_engineer(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    """Create a QA Engineer agent."""
    task_description = "Write and run tests to verify code fixes."

    # Model preference order: codellama:13b -> llama3.1:8b -> llama3.2:latest -> llama3.2:1b
    preferred_models = ["codellama:13b", "llama3.1:8b", "llama3.2:latest", "llama3.2:1b"]

    # Try to get learning-based model preference
    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference("qa")
        if category_model:
            if category_model not in preferred_models:
                preferred_models.insert(0, category_model)
    except ImportError:
        pass

    llm = None
    try:
        # Use the updated get_llm_for_task with preferred models
        llm = router.get_llm_for_task(task_description, preferred_models)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for QA engineer via router: {e}", style="yellow")
        # Fall back to local model
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError("Failed to get LLM for QA engineer agent after all attempts.")

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