# src/crews/internal/diagnostics/agents.py

from crewai import Agent
from rich.console import Console

from src.crews.internal.diagnostics.tools import LogAnalysisTool
from src.crews.internal.developer.agents import create_code_researcher_agent, create_senior_developer_agent

console = Console()

def create_diagnostic_agent(router, inputs: Dict[str, Any], tools: Optional[List] = None) -> Agent:
    """Create a Diagnostic Agent."""
    llm = router.get_llm_for_role("devops_diagnostician")

    # Get the names of the existing coworker agents
    coworker_names = [
        create_code_researcher_agent(router, inputs).name,
        create_senior_developer_agent(router, inputs).name
    ]

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
