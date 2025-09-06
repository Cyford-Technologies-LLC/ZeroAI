from crewai.tools import BaseTool
from src.utils.shared_knowledge import save_agent_learning
from typing import Optional, Any
import datetime

class LearningTool(BaseTool):
    name: str = "Learning Tool"
    description: str = (
        "Saves discovered information or resolutions to a file in the agent's learning directory. "
        "The input to this tool must be a dictionary with 'content' and 'filename' keys."
    )

    def _run(self, input_dict: dict, agent: Optional[Any] = None) -> str:
        # Get the agent's role from the runtime context
        agent_role = getattr(agent, 'role', 'unknown_agent')

        content = input_dict.get('content')
        filename = input_dict.get('filename')

        if not content or not filename:
            return "Error: Both 'content' and 'filename' must be provided."

        if not filename.endswith(".md"):
            filename += ".md"

        dated_filename = f"{datetime.date.today().strftime('%Y-%m-%d')}_{filename}"

        if save_agent_learning(agent_role, dated_filename, content):
            return f"Successfully saved learning to {dated_filename}."
        else:
            return "Failed to save learning."
