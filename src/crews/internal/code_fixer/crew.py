# src/crews/internal/code_fixer/crew.py
from crewai import Crew, Process
from typing import Dict, Any, List
from src.distributed_router import DistributedRouter
from src.config import config
from .agents import create_code_researcher_agent, create_coder_agent, create_tester_agent
from .tasks import analyze_codebase_task, fix_bug_task, write_tests_task, run_tests_task

def get_code_fixer_crew(router, tools, project_config, use_new_memory=False):
    """
    Wrapper function to create the code fixer crew.
    """
    # Prepare inputs based on the project_config
    inputs = {
        "working_dir": project_config.get("crewai_settings", {}).get("working_directory", "/tmp"),
        "project_name": project_config.get("project", {}).get("name", "unknown"),
    }
    # Call the correct crew creation function
    return create_code_fixer_crew(router, inputs, full_output=True)

def create_code_fixer_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    """Creates a code fixer crew using the distributed router."""
    researcher = create_code_researcher_agent(router, inputs)
    coder = create_coder_agent(router, inputs)
    tester = create_tester_agent(router, inputs)

    analyze_task = analyze_codebase_task(researcher, inputs)
    fix_task = fix_bug_task(coder, inputs, context=[analyze_task])
    write_tests_task_instance = write_tests_task(tester, inputs, context=[fix_task])
    run_tests_task_instance = run_tests_task(tester, inputs, context=[write_tests_task_instance])

    return Crew(
        agents=[researcher, coder, tester],
        tasks=[analyze_task, fix_task, write_tests_task_instance, run_tests_task_instance],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )
