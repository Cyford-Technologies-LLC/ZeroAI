# Assuming a file structure where DelegateWorkTool is in a similar location

from pydantic import BaseModel, Field
from crewai_tools import BaseTool

class DelegateWorkToolSchema(BaseModel):
    """Input schema for DelegateWorkTool."""
    coworker: str = Field(..., description="The role/name of the coworker to delegate to.")
    task: str = Field(..., description="The task to delegate.")
    context: str = Field(..., description="The context for the task.")

class DelegateWorkTool(BaseTool):
    name: str = "Delegate work to coworker"
    description: str = "Delegate a specific task to one of the following coworkers."
    args_schema: BaseModel = DelegateWorkToolSchema

    def _run(self, coworker: str, task: str, context: str) -> str:
        # Pre-execution parser to handle malformed LLM output
        if isinstance(task, dict) and 'description' in task:
            task = task['description']
        if isinstance(context, dict) and 'description' in context:
            context = context['description']

        # Existing delegation logic goes here
        # ... your existing logic to delegate the task ...

        return "Task delegated successfully."
