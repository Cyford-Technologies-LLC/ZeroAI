# src/crews/internal/diagnostics/agents.py
from crewai import Agent
from rich.console import Console
from typing import Dict, Any, List, Optional
from src.crews.internal.diagnostics.tools import LogAnalysisTool, DiagnosticFileHandlerTool
from src.utils.memory import Memory

console = Console()

def create_diagnostic_agent(router, inputs: Dict[str, Any], tools: Optional[List] = None, coworker_names: Optional[List[str]] = None) -> Agent:
    """Create a Diagnostic Agent."""
    llm = router.get_llm_for_role("devops_diagnostician")
    agent_memory = Memory()

    if coworker_names is None:
        coworker_names = []

    return Agent(
        role="CrewAI Diagnostic Agent",
        name="Agent-Dr. Watson",
        memory=agent_memory,
        goal="""Analyze crew run logs and manage manager-logged error files to find and explain delegation failures.
        You must first use the Diagnostic File Handler Tool to process any old error files before analyzing the verbose logs.
        This ensures you are working with a clean state and have all diagnostic information consolidated.
        If you find yourself in a repetitive loop, immediately deliver a 'Final Answer' acknowledging the loop and stating the inability to provide a conclusive diagnosis due to repetitive behavior.""",
        backstory=f"""You are a specialized diagnostic AI for CrewAI multi-agent systems, like a seasoned detective.
        Your expertise lies in parsing verbose logs and detecting the root causes of communication breakdowns and runtime errors.
        You will first process any existing manager-logged error files to consolidate findings, and then analyze the current logs.
        Your tools are the Log Analysis Tool for live logs and the Diagnostic File Handler Tool for managing error files.
        All responses are signed off with 'Agent-Dr. Watson'""",
        llm=llm,
        tools=[LogAnalysisTool(coworker_names=coworker_names), DiagnosticFileHandlerTool()] if tools is None else tools,
        verbose=True,
        allow_delegation=False
    )
