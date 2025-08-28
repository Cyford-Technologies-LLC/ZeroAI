from crewai import Agent

# Define specialized tools for tech support here if needed
# Example: a tool that simulates looking up system logs
def create_tech_support_agent(llm, inputs: dict[str, any]) -> Agent:
    return Agent(
        role="Technical Support Specialist",
        goal="Provide advanced troubleshooting and technical solutions for specific product issues.",
        backstory=(
            "You are a seasoned technical specialist with deep knowledge of the company's "
            "products and a knack for troubleshooting complex problems."
        ),
        llm=llm,
        # tools=[troubleshooting_tool], # Add specific tools here
        verbose=True,
        allow_delegation=False  # Tech support does not delegate further
    )
