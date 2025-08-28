from crewai.tools import BaseTool

class DelegatingMathTool(BaseTool):
    name: str = "Delegating Math Tool"
    description: str = "Use this tool to solve a math query by delegating to the Math crew and retrieving the result."

    def __init__(self, crew_manager_instance, inputs):
        super().__init__()
        self.crew_manager = crew_manager_instance
        self.inputs = inputs

    def _run(self, query: str):
        math_crew = self.crew_manager.create_math_crew(self.inputs)
        result = math_crew.kickoff(inputs=self.inputs)
        return result.raw

class ResearchDelegationTool(BaseTool):
    name: str = "Research Delegation Tool"
    description: str = "Use this tool to perform a research inquiry by delegating to the Research crew and retrieving the result."

    def __init__(self, crew_manager_instance, inputs):
        super().__init__()
        self.crew_manager = crew_manager_instance
        self.inputs = inputs

    def _run(self, query: str):
        research_crew = self.crew_manager.create_research_crew(self.inputs)
        result = research_crew.kickoff(inputs=self.inputs)
        return result.raw
