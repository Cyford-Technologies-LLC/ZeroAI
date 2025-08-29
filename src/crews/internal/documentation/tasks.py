# src/crews/documentation/tasks.py

from crewai import Task
from typing import Dict, Any
from crewai import Agent

def update_docs_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    """
    Creates a task for updating documentation based on a bug fix.
    The task description uses inputs to identify the project and bug.
    """
    return Task(
        description=(
            f"Update the documentation in the repository at '{inputs.get('project_path')}' "
            f"based on the recent changes related to bug #{inputs.get('bug_id')}. "
            "Refer to the bug details and code changes provided by the manager agent. "
            "Focus on updating relevant sections like READMEs, changelogs, or specific code comments."
        ),
        agent=agent,
        expected_output="A markdown file reflecting the changes or a report confirming the documentation has been updated.",
    )
