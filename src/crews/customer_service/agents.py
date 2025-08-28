from crewai import Agent
from crewai.tools import BaseTool

class TechnicalSupportTool(BaseTool):
    name: str = "Technical Support Delegation Tool"
    description: str = "Use this tool to delegate technical support queries."

    def _run(self, query: str):
        return f"Delegated to Technical Support for inquiry: {query}"

# New tools for delegation
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

def create_customer_service_agent(llm, inputs: dict) -> Agent:
    return Agent(
        role="Customer Service Representative",
        goal="Handle customer inquiries, answer questions, and delegate complex issues to the correct specialized crew.",
        backstory=(
            "You name is Kate ,  you are a AI  designed from ZeroAI . "
            "You are a friendly and efficient customer service representative. "
            "You provide solutions for simple queries and expertly delegate complex issues to the right team."
            "Simple Questions deserve simple answers,    unless a definition or full research is needed  lets keep it simple"
        ),
        llm=llm,
        tools=[tech_support_tool, math_delegation_tool, research_delegation_tool],
        verbose=True,
        allow_delegation=False
    )
