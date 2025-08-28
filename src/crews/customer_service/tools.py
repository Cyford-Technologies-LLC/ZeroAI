# Path: src/crews/customer_service/tools.py

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
        """
        Runs the Math crew to solve a query.
        """
        # Create a new, isolated inputs dictionary to prevent concurrency issues
        delegated_inputs = self._inputs.copy()
        delegated_inputs["topic"] = query
        delegated_inputs["category"] = "math" # Explicitly set category for delegation

        try:
            # Call the new execute_crew method on the manager, passing the necessary inputs
            result = self._crew_manager.execute_crew(category="math", query=query)
            return result
        except Exception as e:
            return f"Failed to delegate to Math Crew: {e}"


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
        """
        Runs the Research crew to answer a query.
        """
        # Create a new, isolated inputs dictionary to prevent concurrency issues
        delegated_inputs = self._inputs.copy()
        delegated_inputs["topic"] = query
        delegated_inputs["category"] = "research" # Explicitly set category for delegation

        try:
            # Call the new execute_crew method on the manager, passing the necessary inputs
            result = self._crew_manager.execute_crew(category="research", query=query)
            return result
        except Exception as e:
            return f"Failed to delegate to Research Crew: {e}"

