# src/crews/internal/tools/delegate_tool.py

from pydantic import BaseModel, Field
import importlib
import sys

# FIX: Use a robust, version-agnostic import for BaseTool
BaseTool = None
import_paths = [
    "crewai.tools",
    "crewai_tools.tools",
    "crewai.utilities",
    "crewai",
]

for path in import_paths:
    try:
        module = importlib.import_module(path)
        BaseTool = getattr(module, "BaseTool")
        print(f"✅ Successfully imported BaseTool from {path}")
        break
    except (ImportError, AttributeError):
        continue

if not BaseTool:
    print("❌ Failed to import BaseTool from all known paths. Please check your crewai and crewai_tools versions.")
    sys.exit(1)

class DelegateWorkToolSchema(BaseModel):
    """Input schema for DelegateWorkTool."""
    coworker: str = Field(..., description="The role/name of the coworker to delegate to.")
    task: str = Field(..., description="The task to delegate.")
    context: str = Field(..., description="The context for the task.")

class DelegateWorkTool(BaseTool):
    name: str = "Delegate work to coworker"
    description: str = "Delegate a specific task to a coworker."
    args_schema: BaseModel = DelegateWorkToolSchema

    def _run(self, coworker: str, task: str, context: str) -> str:
        # Pre-execution parser to handle malformed LLM output
        # Ensures that task and context are strings, not dictionaries
        if isinstance(task, dict) and 'description' in task:
            task = task['description']
        if isinstance(context, dict) and 'description' in context:
            context = context['description']

        # NOTE: The delegation logic itself is handled by the CrewAI framework
        # when a manager agent is configured. This tool's role is to define
        # the interface for that delegation action.

        # The manager should be prompted to select a coworker and provide
        # the task and context as strings.
        return f"Task '{task}' has been delegated to coworker '{coworker}'."
