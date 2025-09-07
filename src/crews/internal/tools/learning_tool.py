from crewai.tools import BaseTool
from src.utils.shared_knowledge import save_agent_learning
from typing import Optional, Any, Type
import datetime
from pydantic import BaseModel, Field

# 1. Define the Pydantic schema for the tool's input
class LearningToolSchema(BaseModel):
    """Input schema for the Learning Tool."""
    input_dict: dict = Field(
        ...,
        description="A dictionary containing the learning data. It must have 'content' and 'filename' keys."
    )
    agent: Optional[Any] = Field(
        None,
        description="The agent instance calling this tool. The `_run` method will extract the role."
    )

# 2. Add the schema to your BaseTool class
class LearningTool(BaseTool):
    name: str = "Learning Tool"
    description: str = (
        "Saves discovered information or resolutions to a file in the agent's learning directory. "
        "The input to this tool must be a dictionary with 'content' and 'filename' keys."
    )
    # Define the schema using the args_schema attribute
    args_schema: Type[BaseModel] = LearningToolSchema

    def _run(self, input_dict: dict, agent: Optional[Any] = None) -> str:
        # Get the agent's role from the runtime context, as in your original code
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
