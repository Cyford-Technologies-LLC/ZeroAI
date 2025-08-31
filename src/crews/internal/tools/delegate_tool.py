# src/crews/internal/tools/delegate_tool.py
from crewai import Agent
from crewai.utilities import BaseTool
from pydantic import BaseModel, Field
from typing import Union, Dict, Any, List, Optional

class DelegateWorkToolSchema(BaseModel):
    # Change these to expect strings, as required by CrewAI's Pydantic validation
    task: str = Field(..., description="The task to delegate")
    context: str = Field(..., description="The context for the task")
    coworker: str = Field(..., description="The role/name of the coworker to delegate to")

class DelegateWorkTool(BaseTool):
    name: str = "Delegate work to coworker"
    description: str = "Delegate a specific task to a coworker."
    args_schema: BaseModel = DelegateWorkToolSchema
    # Remove coworkers from the tool, as they are passed to the manager's state
    # coworkers: List[Agent] = Field(..., description="List of coworker agents")

    def _run(
        self,
        task: str,  # Expect string here, let the LLM handle it
        context: str, # Expect string here
        coworker: str,
        **kwargs,
    ) -> str:
        # NOTE: The manager's prompt needs to be carefully crafted
        # to ensure it passes strings, not dicts, to this tool.
        # The hierarchical process in crewAI automatically handles this delegation
        # via the manager. This tool simply defines the interface.

        # You can add a guardrail here, but it's better to fix the LLM prompt
        # if the issue persists.
        # if isinstance(task, dict) and "description" in task:
        #     task = task["description"]
        # if isinstance(context, dict) and "description" in context:
        #     context = context["description"]

        # The rest of the delegation is handled by CrewAI's framework.
        return f"Task '{task}' delegated to {coworker}. Context: {context}"

