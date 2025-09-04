from crewai import Crew, Process
from typing import Dict, Any, List
from .agents import create_diagnostic_agent
from .tasks import create_diagnostics_task

def create_diagnostics_crew(router, inputs: Dict[str, Any], tools: List) -> Crew:
    diagnostic_agent = create_diagnostic_agent(router, inputs, tools)
    diagnostics_task = create_diagnostics_task(diagnostic_agent, inputs)

    return Crew(
        agents=[diagnostic_agent],
        tasks=[diagnostics_task],
        process=Process.sequential,
        verbose=inputs.get("verbose", False),
        memory=True
    )