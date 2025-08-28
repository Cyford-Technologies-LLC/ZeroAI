# src/crews/customer_service/tools.py

from typing import Any, Dict
from pydantic import PrivateAttr
from crewai.tools import BaseTool

class DelegatingMathTool(BaseTool):
    name: str = "Delegating Math Tool"
    description: str = "Use this tool to solve a math query by delegating to the Math crew and retrieving the result."

    # Use PrivateAttr to declare custom attributes that are not part of the Pydantic model
    _crew_manager: Any = PrivateAttr()
    _inputs: Dict[str, Any] = PrivateAttr()

    def __init__(self, crew_manager: Any, inputs: Dict[str, Any], **kwargs):
        # Call the parent's __init__ method without passing custom arguments
        super().__init__(**kwargs)
        self._crew_manager = crew_manager
        self._inputs = inputs
        print(f"DEBUG: Initializing DelegatingMathTool from: {__file__}")

    def _run(self, query: str):
        self._inputs["topic"] = query
        math_crew = self._crew_manager.create_crew_for_category("math", self._inputs)
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
        self._inputs["topic"] = query
        research_crew = self._crew_manager.create_crew_for_category("research", self._inputs)
        result = self._crew_manager.execute_crew(research_crew, self._inputs)
        return result.get("output", "Could not complete the research task.")
