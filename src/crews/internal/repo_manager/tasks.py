from crewai import Task
from typing import Dict, Any
from crewai import Agent

def clone_repo_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    return Task(
        description=f"Clone the repository '{inputs.get('repo_url')}' "
                    f"to the local path '{inputs.get('project_path')}' using the Git Operator tool.",
        agent=agent,
        expected_output="A report confirming the repository has been cloned.",
    )

def commit_and_push_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    return Task(
        description=f"Commit and push changes for bug '{inputs.get('bug_id')}' in the repository "
                    f"located at '{inputs.get('project_path')}' using the Git Operator tool.",
        agent=agent,
        expected_output="A report confirming changes have been committed and pushed.",
    )
