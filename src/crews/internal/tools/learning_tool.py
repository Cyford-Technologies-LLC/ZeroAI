from crewai_tools import BaseTool
from src.utils.shared_knowledge import save_agent_learning
from typing import Optional, Any
import datetime


class LearningTool(BaseTool):
    name: str = "Learning Tool"
    description: str = (
        "A tool for agents to save important discoveries, resolutions, or learnings "
        "to a file in their designated learning directory. "
        "The input to this tool must be a JSON string with 'content' and 'filename' keys."
    )

    def __init__(self, agent_role: str):
        super().__init__()
        self.agent_role = agent_role

    def _run(self, input_dict: dict) -> str:
        content = input_dict.get('content')
        filename = input_dict.get('filename')

        if not content or not filename:
            return "Error: Both 'content' and 'filename' must be provided."

        if not filename.endswith(".md"):
            filename += ".md"

        dated_filename = f"{datetime.date.today().strftime('%Y-%m-%d')}_{filename}"

        if save_agent_learning(self.agent_role, dated_filename, content):
            return f"Successfully saved learning to {dated_filename}."
        else:
            return "Failed to save learning."
