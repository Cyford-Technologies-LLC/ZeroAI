# src/crews/internal/diagnostics/agents.py
from crewai import Agent
from crewai.tools import BaseTool
from src.utils.knowledge_utils import get_common_knowledge_strings_strings


from crewai.knowledge.source.string_knowledge_source import StringKnowledgeSource
from rich.console import Console
from typing import Dict, Any, List, Optional
from .tools import LogAnalysisTool, DiagnosticFileHandlerTool
from src.utils.memory import Memory
from src.learning.task_manager import TaskManager
from src.utils.shared_knowledge import get_shared_context_for_agent
import json

console = Console()

# Call the utility to get all common knowledge sources


# FIX: Add a tool for monitoring the task queue
class TaskQueueMonitorTool(BaseTool):
    name: str = "Task Queue Monitor Tool"
    description: str = "Monitors the task queue for failed tasks and retrieves error details."
    task_manager: TaskManager

    def __init__(self, task_manager: TaskManager):
        super().__init__(task_manager=task_manager)

    def _run(self, *args, **kwargs) -> str:
        failed_tasks = self.task_manager.get_failed_tasks()
        if failed_tasks:
            return json.dumps(failed_tasks)
        return "No failed tasks found in the queue."


# FIX: Add a tool for logging and removing tasks
class TaskManagerLoggerTool(BaseTool):
    name: str = "Task Manager Logger Tool"
    description: str = "Logs error details of a failed task and archives it from the queue."
    task_manager: TaskManager

    def __init__(self, task_manager: TaskManager):
        super().__init__(task_manager=task_manager)

    def _run(self, task_id: str, error_details: Dict[str, Any], *args, **kwargs) -> str:
        self.task_manager.log_error(task_id, error_details)
        self.task_manager.archive_task(task_id)
        return f"Logged and archived task {task_id}."


def create_diagnostic_agent(router, inputs: Dict[str, Any], tools: Optional[List] = None,
                            coworker_names: Optional[List[str]] = None) -> Agent:
    """Create a Diagnostic Agent."""
    llm = router.get_llm_for_role("devops_diagnostician")
    agent_memory = Memory()
    task_manager_instance = TaskManager()

    project_location = inputs.get("project_id")
    repository = inputs.get("repository")
    common_knowledge = get_common_knowledge_strings(project_location, repository)



    if coworker_names is None:
        coworker_names = []

    # FIX: Update tools with the new task queue monitoring and logging tools
    diagnostic_tools = [
        TaskQueueMonitorTool(task_manager=task_manager_instance),
        TaskManagerLoggerTool(task_manager=task_manager_instance),
        LogAnalysisTool(coworker_names=coworker_names),
        DiagnosticFileHandlerTool()    ]

    # Load shared team knowledge
    shared_context = get_shared_context_for_agent("CrewAI Diagnostic Agent")

    return Agent(
        role="CrewAI Diagnostic Agent",
        name="Agent-Dr. Watson",
        memory=agent_memory,
        knowledge_sources=[
            common_knowledge  # Use the string knowledge source
        ],
        goal="""Monitor the task queue for failed tasks, analyze the error details, and log them.
        When another crew accepts the task, archive it from the queue.
        If you find yourself in a repetitive loop, immediately deliver a 'Final Answer' acknowledging the loop and stating the inability to provide a conclusive diagnosis due to repetitive behavior.""",
        backstory=f"""You are a specialized diagnostic AI for CrewAI multi-agent systems, like a seasoned detective.
        Your expertise lies in monitoring the task queue, parsing logs, and detecting the root causes of communication breakdowns and runtime errors.
        Your tools are the Task Queue Monitor Tool, Task Manager Logger Tool, Log Analysis Tool, Diagnostic File Handler Tool, and DirectorySearchTool.

        {shared_context}

        All responses are signed off with 'Agent-Dr. Watson'""",
        llm=llm,
        tools=diagnostic_tools if tools is None else tools,
        verbose=True,
        allow_delegation=False
    )
