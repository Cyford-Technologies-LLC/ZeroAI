from crewai import Agent
from crewai.tools import BaseTool

def create_tech_support_agent(llm, inputs: dict) -> Agent:
    return Agent(
        role="Technical Support Specialist",
        goal="Provide solutions for complex technical issues.",
        backstory=(
            "You are a seasoned technical specialist with deep knowledge of the company's "
            "products and a knack for troubleshooting."
        ),
        llm=llm,
        verbose=True,
        allow_delegation=False
    )
