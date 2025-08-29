# Path: crews/coding/agents.py

import sys
from pathlib import Path
from typing import Optional, Dict, Any
from rich.console import Console
from crewai import Agent
from langchain_community.llms.ollama import Ollama
from config import config
from distributed_router import DistributedRouter

console = Console()

def create_coding_developer_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    """Creates a coding developer agent with dynamic LLM selection."""
    llm = None
    try:
        # Use the dynamic router to select the optimal LLM for the 'coding' role
        # FIX: Use `get_llm_for_role` instead of the non-existent `get_llm_for_model`
        llm = router.get_llm_for_role("coding")
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for coding developer via router: {e}", style="yellow")
        # Fallback to local LLM if routing fails
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError("Failed to get LLM for coding developer agent.")

    console.print(
        f"🔗 Coding Developer Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    return Agent(
        role='Coding Developer',
        goal='Generate clean, efficient, and well-documented code for the given problem.',
        backstory=(
            "You are a Senior Software Developer with a deep understanding of multiple programming languages "
            "and a passion for writing robust, high-quality code."
        ),
        llm=llm,
        verbose=config.agents.verbose,
        allow_delegation=False,
    )

def create_qa_engineer_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    """Creates a QA engineer agent with dynamic LLM selection."""
    llm = None
    try:
        # Use the dynamic router to select the optimal LLM for the 'qa' role
        # FIX: Use `get_llm_for_role` instead of the non-existent `get_llm_for_model`
        llm = router.get_llm_for_role("qa")
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for QA engineer via router: {e}", style="yellow")
        # Fallback to local LLM if routing fails
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError("Failed to get LLM for QA engineer agent.")

    console.print(
        f"🔗 QA Engineer Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    return Agent(
        role='QA Engineer',
        goal='Review code for correctness, functionality, and adherence to best practices.',
        backstory=(
            "You are a meticulous QA Engineer with a keen eye for detail. Your mission is to find bugs and "
            "ensure the code meets all requirements before deployment."
        ),
        llm=llm,
        verbose=config.agents.verbose,
        allow_delegation=False,
    )
