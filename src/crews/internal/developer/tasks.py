# src/crews/developer/tasks.py
from crewai import Task
from typing import Dict, Any, List # Combined imports
from crewai import Agent

def analyze_codebase_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    return Task(
        description=f"Analyze the codebase in the directory '{inputs.get('project_path')}' "
                    f"to identify the root cause of bug #{inputs.get('bug_id')}.",
        agent=agent,
        expected_output="A summary of the bug's root cause and the files that need modification.",
    )

def fix_bug_task(agent: Agent, inputs: Dict[str, Any], context: List[Task]) -> Task:
    return Task(
        description=f"Fix bug #{inputs.get('bug_id')} in the project ."
                    "**CRITICAL:** Review the codebase analysis provided in the context from the Researcher. Use ONLY this information to understand the issue and identify the relevant files. Implement the code solution to fix the bug, ensuring the fix aligns with the details provided in the context. DO NOT use your file tools unless the context explicitly requires you to."
                    f"If you have issues  getting details from the project manager check the project details yourself (knowledge/internal_crew/{inputs.get('project_path')}/project_config.yaml"
                    "Prioritize Docker Composer Over Git Clones"
                    "based on the root cause analysis.",
        agent=agent,
        context=context,
        expected_output="The updated code files with the bug fix applied.",
    )

def write_tests_task(agent: Agent, inputs: Dict[str, Any], context: List[Task]) -> Task:
    return Task(
        description=f"Write test cases for bug #{inputs.get('bug_id')} in the project at '{inputs.get('project_path')}' "
                    "to verify the fix.",
        agent=agent,
        context=context,
        expected_output="The newly created test files.",
    )

def run_tests_task(agent: Agent, inputs: Dict[str, Any], context: List[Task]) -> Task:
    return Task(
        description=f"Execute the tests for bug #{inputs.get('bug_id')} in the project at '{inputs.get('project_path')}' "
                    "and report the results.",
        agent=agent,
        context=context,
        expected_output="A report indicating whether the tests passed or failed.",
    )

