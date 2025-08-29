# src/crews/developer/crew.py
from crewai import Crew, Process
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from .agents import create_researcher_agent, create_coder_agent, create_tester_agent
from .tasks import analyze_codebase_task, fix_bug_task, write_tests_task, run_tests_task


def create_developer_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    """Creates a developer crew using the distributed router."""
    researcher = create_researcher_agent(router, inputs)
    coder = create_coder_agent(router, inputs)
    tester = create_tester_agent(router, inputs)

    analyze_task = analyze_codebase_task(researcher, inputs)
    fix_task = fix_bug_task(coder, inputs, context=[analyze_task])
    write_tests_task = write_tests_task(tester, inputs, context=[fix_task])
    run_tests_task = run_tests_task(tester, inputs, context=[write_tests_task])

    return Crew(
        agents=[researcher, coder, tester],
        tasks=[analyze_task, fix_task, write_tests_task, run_tests_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )
