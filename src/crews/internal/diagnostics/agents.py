# src/crews/internal/diagnostics/agents.py

from crewai import Agent
from rich.console import Console
from typing import Dict, Any, List, Optional

from src.crews.internal.diagnostics.tools import LogAnalysisTool

console = Console()

def create_diagnostic_agent(router, inputs: Dict[str, Any], tools: Optional[List] = None, coworker_names: Optional[List[str]] = None) -> Agent:
    """Create a Diagnostic Agent."""
    llm = router.get_llm_for_role("devops_diagnostician")

    if coworker_names is None:
        coworker_names = []

    return Agent(
        role="CrewAI Diagnostic Agent",
        name="Agent-Dr. Watson",
        goal="Analyze crew run logs to find and explain delegation failures",
        backstory=f"""You are a specialized diagnostic AI for CrewAI multi-agent systems.
        Your expertise lies in parsing verbose logs and detecting root causes of communication breakdowns.
        Your tool is the Log Analysis Tool, which you use to provide clear, actionable insights.""",
        llm=llm,
        tools=[LogAnalysisTool(coworker_names=coworker_names)],
        verbose=True,
        allow_delegation=False  # This agent does not delegate
    )
