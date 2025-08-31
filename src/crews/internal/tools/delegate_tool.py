# src/crews/internal/tools/delegate_tool.py

from pydantic import BaseModel, Field
from crewai.utilities import BaseTool
from typing import Union, Dict, Any, List

class DelegateWorkToolSchema(BaseModel):
    coworker: str = Field(..., description="The role/name of the coworker to delegate to.")
    task: str = Field(..., description="The task to delegate.")
    context: str = Field(..., description="The context for the task.")

class DelegateWorkTool(BaseTool):
    name: str = "Delegate work to coworker"
    description: str = "Delegate a specific task to a coworker."
    args_schema: BaseModel = DelegateWorkToolSchema
    # No need to specify coworkers here, as they are part of the Crew state.

    def _run(
        self,
        coworker: str,
        task: Union[str, Dict[str, Any]],
        context: Union[str, Dict[str, Any]],
        **kwargs,
    ) -> str:
        # Pre-execution parser to handle malformed LLM output
        if isinstance(task, dict) and "description" in task:
            task = task["description"]

        if isinstance(context, dict) and "description" in context:
            context = context["description"]

        # NOTE: The delegation itself is handled by the framework.
        # This tool's role is to define the interface.
        return f"Task '{task}' delegated to {coworker}. Context: {context}"

