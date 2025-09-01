# src/crews/developer/crew.py
from crewai import Crew, Process
from typing import Dict, Any
from distributed_router import DistributedRouter
from src.config import config
from .agents import create_code_researcher_agent, create_senior_developer_agent, create_junior_developer_agent, \
    create_qa_engineer_agent
from .tasks import analyze_codebase_task, fix_bug_task, write_tests_task, run_tests_task


def get_developer_crew(router, tools, project_config, use_new_memory=False):
    """
    Wrapper function to create the developer crew.
    """
    inputs = {
        "working_dir": project_config.get("crewai_settings", {}).get("working_directory", "/tmp"),
        "project_name": project_config.get("project", {}).get("name", "unknown"),
    }
    return create_developer_crew(router, inputs, full_output=True)


def create_developer_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    """Creates a developer crew using the distributed router."""
    researcher = create_code_researcher_agent(router, inputs)
    senior_developer = create_senior_developer_agent(router, inputs)
    junior_developer = create_junior_developer_agent(router, inputs)
    tester = create_qa_engineer_agent(router, inputs)

    analyze_task = analyze_codebase_task(researcher, inputs)

    # Use senior_developer instead of the undefined 'coder'
    fix_task = fix_bug_task(senior_developer, inputs, context=[analyze_task])

    # Ensure correct task context chaining
    write_tests_task_instance = write_tests_task(tester, inputs, context=[fix_task])
    run_tests_task_instance = run_tests_task(tester, inputs, context=[write_tests_task_instance])

    return Crew(
        agents=[researcher, senior_developer, junior_developer, tester],
        tasks=[analyze_task, fix_task, write_tests_task_instance, run_tests_task_instance],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )
