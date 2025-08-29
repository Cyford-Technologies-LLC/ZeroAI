# src/crews/internal/research/tasks.py

from crewai import Task, Agent
from typing import Dict, Any

def internal_research_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    return Task(
        description=f"""
        Perform a focused research task related to the project in working directory '{inputs.get('working_directory')}'.
        Research topic: {inputs.get('topic')}.
        """,
        agent=agent,
        expected_output="A detailed research report on the specified topic."
    )

def internal_analysis_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    return Task(
        description=f"""
        Analyze the research report to find relevant insights for the project.
        Project context: '{inputs.get('topic')}'
        """,
        agent=agent,
        expected_output="A summary of the most important findings from the research."
    )
