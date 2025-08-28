from crewai import Agent
from crewai.tools import BaseTool

# Your delegation tool for the customer service agent
class TechnicalSupportTool(BaseTool):
    name: str = "Technical Support Delegation Tool"
    description: str = "Tool to delegate technical support queries."

    def _run(self, query: str):
        return f"Delegated to Technical Support for inquiry: {query}"

tech_support_tool = TechnicalSupportTool()

def create_customer_service_agent(llm, inputs: dict[str, any]) -> Agent:
    return Agent(
        role="Customer Service Representative",
        goal="Handle customer inquiries, answer questions, and delegate complex issues.",
        backstory=(
            "You are a friendly and efficient customer service representative. "
            "Your job is to understand the customer's request and provide a solution "
            "or delegate it to the appropriate specialized crew if needed."
        ),
        llm=llm,
        tools=[tech_support_tool],
        verbose=True,
        allow_delegation=True
    )
