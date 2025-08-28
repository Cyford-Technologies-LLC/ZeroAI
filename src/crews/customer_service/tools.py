# src/crews/customer_service/tools.py

from typing import Any, Dict
from pydantic import PrivateAttr
from crewai.tools import BaseTool
from crewai import CrewOutput


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
        self._inputs["topic"] = query
        math_crew = self._crew_manager._create_specialized_crew(category="math", inputs=self._inputs)

        crew_output = self._crew_manager.execute_crew(math_crew, self._inputs)

        if isinstance(crew_output, CrewOutput):
            return crew_output.result
        else:
            return "Could not solve the math problem. Unexpected output type from the crew."


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
        research_crew = self._crew_manager._create_specialized_crew(category="research", inputs=self._inputs)

        crew_output = self._crew_manager.execute_crew(research_crew, self._inputs)

        if isinstance(crew_output, CrewOutput):
            return crew_output.result
        else:
            return "Could not complete the research task. Unexpected output type from the crew."
