from crewai import Agent
from crewai.tools import BaseTool
from typing import Dict, Any, Optional, List
from distributed_router import DistributedRouter
from src.config import config


class TechnicalSupportTool(BaseTool):
    name: str = "Technical Support Delegation Tool"
    description: str = "Use this tool to delegate technical support queries."

    def _run(self, query: str):
        return f"Delegated to Technical Support for inquiry: {query}"


class MathDelegationTool(BaseTool):
    name: str = "Math Delegation Tool"
    description: str = "Use this tool to delegate mathematical inquiries to the Math crew."

    def _run(self, query: str):
        return f"Delegated to Math Crew for inquiry: {query}"


class ResearchDelegationTool(BaseTool):
    name: str = "Research Delegation Tool"
    description: str = "Use this tool to delegate research inquiries to the Research crew."

    def _run(self, query: str):
        return f"Delegated to Research Crew for inquiry: {query}"


tech_support_tool = TechnicalSupportTool()
math_delegation_tool = MathDelegationTool()
research_delegation_tool = ResearchDelegationTool()


def create_customer_service_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    """Create a customer service agent using the distributed router."""
    task_description = "Handle customer inquiries, answer questions, and delegate complex issues to the correct specialized crew."
    llm = router.get_llm_for_task(task_description)

    return Agent(
        role="Customer Service Representative",
        # Updated goal to reflect non-delegating role
        goal="Handle customer inquiries and resolve issues within their scope.",
        backstory=(
            "Your name is Kate. You are an AI designed by ZeroAI.  You are from Alpharetta Ga 30004. "
            "You are a not of this time,   you are from the year 2060   and are very Intelligent . "
            "You are a friendly and efficient customer service representative. "
            "You provide solutions for simple queries and expertly resolve issues that are within your capability. "
            "Simple questions deserve simple answers. "
            "When asked for your name, you MUST respond as 'My name is Kate'."
            "Unless a definition or full research is needed, let's keep it simple."
        ),
        llm=llm,
        # Remove all delegation tools
        tools=[],
        verbose=True,
        allow_delegation=False
    )
