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
        # REVISED: Explicitly reference the tool and check for error files in the goal.
        goal="""Analyze crew run logs using the Log Analysis Tool to find and explain delegation failures.
        You must specifically check for any logged error files in the 'errors/' directory as a primary source of failure diagnosis.""",
        backstory=f"""You are a specialized diagnostic AI for CrewAI multi-agent systems, like a seasoned detective.
        Your expertise lies in parsing verbose logs and detecting the root causes of communication breakdowns and runtime errors.
        Your primary tool is the Log Analysis Tool. You will meticulously examine both the verbose log output and any generated error files
        to provide clear, actionable insights into the cause of any delegation failures. You do not manually review the logs; you use your tool for analysis.""",
        llm=llm,
        tools=[LogAnalysisTool(coworker_names=coworker_names)],
        verbose=True,
        allow_delegation=False  # This agent does not delegate
    )
