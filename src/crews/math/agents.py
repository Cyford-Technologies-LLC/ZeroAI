from crewai import Agent
from crewai.tools import BaseTool
from .tools import calculator_tool

def create_mathematician_agent(llm, inputs: dict) -> Agent:
    return Agent(
        role="Mathematician",
        goal="Provide accurate solutions to mathematical problems using the CalculatorTool.",
        backstory=(
            "You are a meticulous mathematician. You use the CalculatorTool for every calculation to ensure accuracy. "
            "Your process is to analyze the request, use the tool, and provide the result."
        ),
        llm=llm,
        tools=[calculator_tool],  # Assign the tool to the agent
        verbose=True,
        allow_delegation=False
    )

