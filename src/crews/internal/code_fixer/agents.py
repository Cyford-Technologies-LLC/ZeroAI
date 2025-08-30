# src/crews/developer/agents.py
from crewai import Agent
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from tools.file_tool import file_tool
from src.utils.memory import Memory




# Create memory instance
memory = Memory()



def create_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    task_description = "Analyze bug reports, code, and project context."
    llm = router.get_llm_for_task(task_description)
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
    llm = router.get_llm_for_task(task_description)
    return Agent(
        role="Senior Developer",
                memory=memory,  # Add memory here
                name="Cody",
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

        goal="Implement bug fixes and write clean, maintainable code.",
        backstory="A seasoned developer with a knack for solving complex coding problems.",
        llm=llm,
        tools=[file_tool],
        verbose=config.agents.verbose,
        allow_delegation=False
    )

def create_tester_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    task_description = "Write and run tests to verify code fixes."
    llm = router.get_llm_for_task(task_description)
    return Agent(
        role="QA Engineer",
        goal="Ensure all bug fixes are verified with comprehensive tests.",
        backstory="A meticulous QA engineer who ensures code quality and correctness.",
        llm=llm,
        tools=[file_tool],
        verbose=config.agents.verbose,
        allow_delegation=False
    )
