# src/crews/internal/code_fixer/agents.py

from crewai import Agent
from typing import Dict, Any, Optional, List
from src.distributed_router import DistributedRouter
from src.config import config
from src.crews.internal.tools.git_tool import GitTool, FileTool # Corrected import
from src.utils.memory import Memory
from src.utils.shared_knowledge import get_shared_context_for_agent
from langchain_ollama import OllamaLLM
from rich.console import Console

console = Console()

def get_code_fixer_llm(router: DistributedRouter, category: str) -> Any:
    """
    Selects the optimal LLM for code fixer tasks,
    with a fallback mechanism.
    """
    llm = None
    try:
        task_description = f"Perform {category} tasks."
        llm = router.get_llm_for_task(task_description)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for {category} agent via router: {e}", style="yellow")
        # Ensure the fallback uses the correct config for base_url and model name
        llm = OllamaLLM(model=config.model.name, base_url=config.model.base_url)

    if not llm:
        raise ValueError(f"Failed to get LLM for {category} agent after all attempts.")

    console.print(
        f"🔗 {category.capitalize()} Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")
    return llm

def create_code_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None, coworkers: Optional[List] = None) -> Agent:
    llm = get_code_fixer_llm(router, category="code_research")
    agent_memory = Memory()

    project_location = inputs.get("project_id")
    repository = inputs.get("repository")



    return Agent(
        role="Code Researcher",
        name="Timothy",
        memory=agent_memory,
        coworkers=coworkers if coworkers is not None else [],
        learning={
                "enabled": True,
                "learning_rate": 0.05,
                "feedback_incorporation": "immediate",
                "adaptation_strategy": "progressive"
            },
        personality={
                "traits": ["analytical", "detail-oriented", "methodical", "curious"],
                "quirks": ["uses scientific analogies", "responds with examples", "starts sentences with 'Hmm, let's see...'"],
                "communication_preferences": ["prefers open-ended questions", "responds with potential solutions"]
            },
        communication_style={
                "formality": "semi-professional",
                "verbosity": "descriptive",
                "tone": "cooperative",
                "technical_level": "intermediate"
            },
        knowledge_sources=[
            f"Project Directory:  knowledge/internal_crew/{project_location}"
            f"GIT Repository: {repository} ."
        ],
        goal="Understand and analyze bug reports to find the root cause.",
        backstory=f"An expert in software analysis, specializing in finding code issues.\n\n{get_shared_context_for_agent('Code Researcher')}\n\nResponses are signed with the name Timothy.",
        llm=llm,
        tools=tools,
        verbose=config.agents.verbose,
        allow_delegation=False,
    )


def create_coder_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None, coworkers: Optional[List] = None) -> Agent:
    llm = get_code_fixer_llm(router, category="coding")
    agent_memory = Memory()

    project_location = inputs.get("project_id")
    repository = inputs.get("repository")



    return Agent(
        role="Senior Developer",
        name="Anthony Gates",
        memory=agent_memory,
        coworkers=coworkers if coworkers is not None else [],
        learning={
                "enabled": True,
                "learning_rate": 0.05,
                "feedback_incorporation": "immediate",
                "adaptation_strategy": "progressive"
            },
        personality={
                "traits": ["experienced", "problem-solver", "mentor"],
                "quirks": ["prefers clean code", "uses analogies to explain complex issues"],
                "communication_preferences": ["prefers direct questions", "responds with practical examples"]
            },
        communication_style={
                "formality": "professional",
                "verbosity": "descriptive",
                "tone": "confident",
                "technical_level": "expert"
            },
        knowledge_sources=[
            f"Project Directory:  knowledge/internal_crew/{project_location}"
            f"GIT Repository: {repository} ."
        ],
        goal="Implement bug fixes and write clean, maintainable code.",
        backstory=f"A seasoned developer with a knack for solving complex coding problems.\n\n{get_shared_context_for_agent('Senior Developer')}\n\nResponses are signed with the name Anthony Gates.",
        llm=llm,
        tools=tools,
        verbose=config.agents.verbose,
        allow_delegation=False
    )


def create_tester_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None, coworkers: Optional[List] = None) -> Agent:
    llm = get_code_fixer_llm(router, category="testing")
    agent_memory = Memory()

    project_location = inputs.get("project_id")
    repository = inputs.get("repository")


    return Agent(
        role="QA Engineer",
        name="Emily",
        memory=agent_memory,
        coworkers=coworkers if coworkers is not None else [],
        learning={
                "enabled": True,
                "learning_rate": 0.05,
                "feedback_incorporation": "immediate",
                "adaptation_strategy": "progressive"
            },
        personality={
                "traits": ["meticulous", "thorough", "critical thinker"],
                "quirks": ["prefers clear instructions", "questions assumptions"],
                "communication_preferences": ["prefers direct questions", "responds with potential issues"]
            },
        communication_style={
                "formality": "professional",
                "verbosity": "detailed",
                "tone": "objective",
                "technical_level": "expert"
            },
        knowledge_sources=[
            f"Project Directory:  knowledge/internal_crew/{project_location}"
            f"GIT Repository: {repository} ."
        ],
        goal="Ensure all bug fixes are verified with comprehensive tests.",
        backstory=f"A meticulous QA engineer who ensures code quality and correctness.\n\n{get_shared_context_for_agent('QA Engineer')}\n\nResponses are signed with the name Emily.",
        llm=llm,
        tools=tools,
        verbose=config.agents.verbose,
        allow_delegation=False
    )
