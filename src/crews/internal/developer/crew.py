# src/crews/developer/crew.py
from crewai import Crew, Process
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from .agents import create_code_researcher, create_senior_developer, create_junior_developer ,  create_qa_engineer
from .tasks import analyze_codebase_task, fix_bug_task, write_tests_task, run_tests_task


def create_developer_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    """Creates a developer crew using the distributed router."""
    researcher = create_code_researcher(router, inputs)
    senior_developer = create_senior_developer(router, inputs)
    junior_developer = create_junior_developer(router, inputs)
    tester = create_qa_engineer(router, inputs)

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
