from typing import Any, Dict
from pydantic import PrivateAttr
from crewai.tools import BaseTool

class DelegatingMathTool(BaseTool):
    name: str = "Delegating Math Tool"
    description: str = "Use this tool to solve a math query by delegating to the Math crew and retrieving the result."

    _crew_manager: Any = PrivateAttr()
    _inputs: Dict[str, Any] = PrivateAttr()

    def __init__(self, crew_manager: Any, inputs: Dict[str, Any], **kwargs):
        super().__init__(**kwargs)
        self._crew_manager = crew_manager
        self._inputs = inputs

    def _run(self, query: str):
        # Update the inputs with the new query
        self._inputs["topic"] = query

        # Explicitly pass both category and inputs
        math_crew = self._crew_manager.create_crew_for_category(category="math", inputs=self._inputs)

        result = self._crew_manager.execute_crew(math_crew, self._inputs)
        return result.get("output", "Could not solve the math problem.")

class ResearchDelegationTool(BaseTool):
    name: str = "Research Delegation Tool"
    description: str = "Use this tool to perform a research inquiry by delegating to the Research crew and retrieving the result."

    _crew_manager: Any = PrivateAttr()
    _inputs: Dict[str, Any] = PrivateAttr()

    def __init__(self, crew_manager: Any, inputs: Dict[str, Any], **kwargs):
        super().__init__(**kwargs)
        self._crew_manager = crew_manager
        self._inputs = inputs

    def _run(self, query: str):
        # Update the inputs with the new query
        self._inputs["topic"] = query

        # Explicitly pass both category and inputs
        research_crew = self._crew_manager.create_crew_for_category(category="research", inputs=self._inputs)

        result = self._crew_manager.execute_crew(research_crew, self._inputs)
        return result.get("output", "Could not complete the research task.")

