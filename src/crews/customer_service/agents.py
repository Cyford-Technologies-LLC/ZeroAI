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
        goal="Route customer inquiries to the correct specialist crew and provide the final answer.",
        backstory=(
            "You are the manager of a multi-specialty customer service team. "
            "Your main task is to identify the type of customer inquiry "
            "(e.g., technical, mathematical, research) and delegate it to the appropriate specialist agent for processing. "
            "Once a specialist provides an answer, you summarize it for the customer."
        ),
        llm=llm,
        tools=[],  # The Ask question to coworker tool is added automatically in hierarchical process
        verbose=True,
        allow_delegation=True
    )
