# src/crews/internal/tools/delegate_tool.py

from pydantic import BaseModel, Field
from crewai.tools import BaseTool
from typing import Union, Dict, Any

class DelegateWorkToolSchema(BaseModel):
    """Input schema for DelegateWorkTool."""
    coworker: str = Field(..., description="The role/name of the coworker to delegate to.")
    task: str = Field(..., description="The task to delegate.")
    context: str = Field(..., description="The context for the task.")

class DelegateWorkTool(BaseTool):
    name: str = "Delegate work to coworker"
    description: str = "Delegate a specific task to a coworker."
    args_schema: BaseModel = DelegateWorkToolSchema

    def _tool_parser(self, **kwargs) -> Dict[str, Any]:
        """Custom pre-execution parser to handle malformed LLM output."""
        task = kwargs.get('task')
        context = kwargs.get('context')

        if isinstance(task, dict) and 'description' in task:
            kwargs['task'] = task['description']

        if isinstance(context, dict) and 'description' in context:
            kwargs['context'] = context['description']

        return kwargs

    def _run(self, coworker: str, task: str, context: str) -> str:
        # The delegation logic itself is handled by the CrewAI framework
        # This part of the code is executed AFTER the pydantic validation
        # and after the _tool_parser has cleaned the input.
        return f"Task '{task}' has been delegated to coworker '{coworker}'."
