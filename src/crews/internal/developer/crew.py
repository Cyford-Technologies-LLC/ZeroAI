# src/crews/developer/crew.py
from crewai import Crew, Process
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from .agents import create_code_researcher_agent, create_senior_developer_agent, create_junior_developer_agent,  create_qa_engineer_agent
from .tasks import analyze_codebase_task, fix_bug_task, write_tests_task, run_tests_task

def get_developer_crew(router, tools, project_config, use_new_memory=False):
    """
    Wrapper function to create the documentation crew using the existing create_documentation_crew function.

    Args:
        router: The DistributedRouter instance for model selection
        tools: List of tools to use
        project_config: Project configuration
        use_new_memory: Whether to use new memory instances for agents

    Returns:
        A Crew instance for documentation tasks
    """
    # Prepare inputs based on the project_config
    inputs = {
        "working_dir": project_config.get("crewai_settings", {}).get("working_directory", "/tmp"),
        "project_name": project_config.get("project", {}).get("name", "unknown"),
        # Add any other inputs needed by the documentation crew
    }

    # Create and return the documentation crew
    return create_documentation_crew(router, inputs, full_output=True)


def create_developer_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    """Creates a developer crew using the distributed router."""
    researcher = create_code_researcher_agent(router, inputs)
    senior_developer = create_senior_developer_agent(router, inputs)
    junior_developer = create_junior_developer_agent(router, inputs)
    tester = create_qa_engineer_agent(router, inputs)

    analyze_task = analyze_codebase_task(researcher, inputs)
    fix_task = fix_bug_task(coder, inputs, context=[analyze_task])
    write_tests_task = write_tests_task(tester, inputs, context=[fix_task])
    run_tests_task = run_tests_task(tester, inputs, context=[write_tests_task])

    return Crew(
        agents=[researcher, senior_developer , junior_developer , tester],
        tasks=[analyze_task, fix_task, write_tests_task, run_tests_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )
