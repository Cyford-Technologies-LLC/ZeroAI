# src/crews/internal/tools/delegate_tool.py
from crewai.tools import BaseTool
from crewai import Agent
from pydantic import BaseModel, Field
from typing import Union, Dict, Any, List

class DelegateWorkToolSchema(BaseModel):
    task: Union[str, Dict[str, Any]] = Field(..., description="The task to delegate")
    context: Union[str, Dict[str, Any]] = Field(..., description="The context for the task")
    coworker: str = Field(..., description="The role/name of the coworker to delegate to")

class DelegateWorkTool(BaseTool):
    name: str = "Delegate work to coworker"
    description: str = "Delegate a specific task to a coworker."
    args_schema: BaseModel = DelegateWorkToolSchema
    coworkers: List[Agent] = Field(..., description="List of coworker agents")

    def _run(
        self,
        task: Union[str, Dict[str, Any]],
        context: Union[str, Dict[str, Any]],
        coworker: str,
        **kwargs,
    ) -> str:
        # If task is a dictionary, extract the description
        if isinstance(task, dict) and "description" in task:
            task = task["description"]

        # If context is a dictionary, extract the description
        if isinstance(context, dict) and "description" in context:
            context = context["description"]
        
        coworker_agent = self._get_coworker(coworker, **kwargs)
        if coworker_agent:
            return self._execute(coworker_agent, task, context)
        else:
            return f"Coworker with role/name '{coworker}' not found."

    def _get_coworker(self, coworker_name: str, **kwargs) -> Agent | None:
        for agent in self.coworkers:
            if agent.name == coworker_name or agent.role == coworker_name:
                return agent
        return None

    def _execute(self, agent: Agent, task: str, context: str) -> str:
        # In a real-world scenario, this would trigger a sub-crew.
        # For simplicity, we can return a confirmation message.
        return f"Task '{task}' delegated to {agent.name}. Context: {context}"

