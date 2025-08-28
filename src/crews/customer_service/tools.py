# src/crews/customer_service/tools.py

from crewai.tools import BaseTool

class DelegatingMathTool(BaseTool):
    name: str = "Delegating Math Tool"
    description: str = "Use this tool to solve a math query by delegating to the Math crew and retrieving the result."

    def __init__(self, crew_manager, **kwargs):
        # Call the parent's __init__ method without passing the custom argument.
        super().__init__(**kwargs)

        print(f"DEBUG: Initializing DelegatingMathTool from: {__file__}")

        # After the parent is initialized, set your custom attributes.
        self.crew_manager = crew_manager
        self.inputs = kwargs.get('inputs', {})

    def _run(self, query: str):
        math_crew = self.crew_manager.create_math_crew(self.inputs)
        result = math_crew.kickoff(inputs=self.inputs)
        return result.raw

class ResearchDelegationTool(BaseTool):
    name: str = "Research Delegation Tool"
    description: str = "Use this tool to perform a research inquiry by delegating to the Research crew and retrieving the result."

    def __init__(self, crew_manager, **kwargs):
        # Call the parent's __init__ method.
        super().__init__(**kwargs)

        # Set custom attributes.
        self.crew_manager = crew_manager
        self.inputs = kwargs.get('inputs', {})

    def _run(self, query: str):
        research_crew = self.crew_manager.create_research_crew(self.inputs)
        result = research_crew.kickoff(inputs=self.inputs)
        return result.raw
