from crewai.tools import BaseTool
from pydantic import Field
from typing import Optional, List
from rich.console import Console

console = Console()


class InternalPeerCheckTool(BaseTool):
    name: str = "Internal Peer Check Tool"
    description: str = "Reports the names of all agents in the manager's list of coworkers."
    coworkers: Optional[List] = Field(None, description="The list of all coworker agents.")

    def _run(self, input_data: str = None) -> str:
        """
        Lists the names of all known coworker agents.
        Input is not used; it is simply triggered to perform the check.
        """
        if not self.coworkers:
            return "No coworkers were provided to the manager. Delegation will not be possible."

        coworker_names = [coworker.name for coworker in self.coworkers if hasattr(coworker, 'name')]

        if not coworker_names:
            return "Coworker list is populated but contains no agents with a 'name' attribute. Check agent creation."

        return f"Manager is aware of the following coworkers: {', '.join(coworker_names)}"
