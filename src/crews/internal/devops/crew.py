# src/crews/devops/crew.py
from crewai import Crew, Process
from typing import Dict, Any
from src.distributed_router import DistributedRouter
from src.config import config
from src.crews.internal.devops.agents import create_kubernetes_agent, create_senior_devops_agent, create_docker_agent, \
    create_devops_engineer_agent
from .tasks import analyze_codebase_task, fix_bug_task, write_tests_task, run_tests_task


def get_devops_crew(router, tools, project_config, use_new_memory=False):
    """
    Wrapper function to create the devops crew.
    """
    inputs = {
        "working_dir": project_config.get("crewai_settings", {}).get("working_directory", "/tmp"),
        "project_name": project_config.get("project", {}).get("name", "unknown"),
    }
    return create_devops_crew(router, inputs, full_output=True)


def create_devops_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    """Creates a devops crew using the distributed router."""
    kubernetes = create_kubernetes_agent(router, inputs)
    senior_devops = create_senior_devops_agent(router, inputs)
    docker_devops = create_docker_agent(router, inputs)
    devops_engineer = create_devops_engineer_agent(router, inputs)


    return Crew(
        agents=[kubernetes, senior_devops, docker_devops, devops_engineer],
        tasks=[],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output
    )
