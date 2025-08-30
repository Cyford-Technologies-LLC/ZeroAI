from crewai import Agent
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from tools.git_tool import git_tool, file_tool
from src.utils.memory import Memory



# Create memory instance
memory = Memory()



def create_git_operator_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    task_description = "Perform Git and file system operations."
    preferred_models = ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"]

    # Try to get learning-based model preference
    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference("repo_management")
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
        console.print(f"⚠️ Failed to get optimal LLM for repo manager agent via router: {e}", style="yellow")
        # Fall back to local model
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError("Failed to get LLM for repo manager agent after all attempts.")

    console.print(
        f"🔗 Repo Manager Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")



    llm = router.get_llm_for_task(task_description)
    return Agent(
        role="Git Operator",
        name="Deon Sanders",
        memory=memory,  # Add memory here
        learning={
                "enabled": True,
                "learning_rate": 0.05,
                "feedback_incorporation": "immediate",
                "adaptation_strategy": "progressive"
            },
        personality={
                "traits": ["analytical", "detail-oriented", "methodical"],
                "quirks": ["always cites research papers", "uses scientific analogies"],
                "communication_preferences": ["prefers direct questions", "responds with examples"]
            },
        communication_style={
                "formality": "professional",
                "verbosity": "concise",
                "tone": "authoritative",
                "technical_level": "expert"
            },
        resources=[
                "testing_frameworks.md",
                "code_quality_guidelines.pdf",
                "https://testing-best-practices.com"
            ],
        expertise=[
                "GIT", "Bit Bucket"
            ],
        expertise_level=9.2,  # On a scale of 1-10

        goal="Execute Git commands and file manipulations to manage project repositories.",
        backstory="An automated system for performing repository management tasks.",
        llm=llm,
        tools=[git_tool, file_tool],
        verbose=config.agents.verbose,
        allow_delegation=False
    )

