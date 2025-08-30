# src/crews/documentation/agents.py
from crewai import Agent
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from tools.file_tool import file_tool

def create_writer_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    task_description = "Generate or update documentation based on project changes."
    preferred_models = ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"]

    # Try to get learning-based model preference
    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference("documentation")
        if category_model:
            if category_model not in preferred_models:
                preferred_models.insert(0, category_model)
    except ImportError:
        pass  # Learning module not available


    llm = router.get_llm_for_task(task_description)
    return Agent(
        role="Documentation Writer",
        goal="Create clear and concise documentation for software projects.",
        backstory="A skilled technical writer who translates complex code into understandable documentation.",
        llm=llm,
        tools=[file_tool],
        verbose=config.agents.verbose,
        allow_delegation=False
    )
