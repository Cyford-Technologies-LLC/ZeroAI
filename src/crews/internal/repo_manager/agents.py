from crewai import Agent
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from tools.git_tool import git_tool, file_tool

def create_git_operator_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    task_description = "Perform Git and file system operations."
    llm = router.get_llm_for_task(task_description)
    return Agent(
        role="Git Operator",
        goal="Execute Git commands and file manipulations to manage project repositories.",
        backstory="An automated system for performing repository management tasks.",
        llm=llm,
        tools=[git_tool, file_tool],
        verbose=config.agents.verbose,
        allow_delegation=False
    )

