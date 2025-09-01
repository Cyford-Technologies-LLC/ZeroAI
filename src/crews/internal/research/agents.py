# src/crews/internal/research/agents.py

import inspect
import importlib
from crewai import Agent
from crewai.tools import BaseTool
from typing import Dict, Any, List, Optional
from distributed_router import DistributedRouter
from src.config import config
from rich.console import Console
from src.utils.memory import Memory
from pathlib import Path
import os
import yaml
from crewai_tools import SerperDevTool, GithubSearchTool # Correct import
#from src.tools.git_tool import GitTool
console = Console()


class ProjectConfigReaderTool(BaseTool):
    name: str = "Project Config Reader"
    description: str = "Reads project details from a YAML file based on the project location."
    project_location: str

    def __init__(self, project_location: str):
        super().__init__(project_location=project_location)

    def _run(self, *args, **kwargs):
        config_path = Path("knowledge") / "internal_crew" / self.project_location / "project_config.yaml"
        if config_path.is_file():
            with open(config_path, 'r') as f:
                return yaml.safe_load(f)
        else:
            return f"Error: No project configuration found for '{self.project_location}'."


class OnlineSearchTool(BaseTool):
    name: str = "Online Search"
    description: str = "Performs online searches to find information from websites."

    def __init__(self):
        super().__init__()
        self.search_tool = SerperDevTool()

    def _run(self, query: str):
        return self.search_tool.run(query)


def get_online_search_tool():
    """Helper function to get a configured online search tool."""
    return OnlineSearchTool()


def get_research_llm(router: DistributedRouter, category: str = "research",
                     preferred_models: Optional[List] = None) -> Any:
    preferred_models = preferred_models or ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llama3.2:1b"]

    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference(category)
        if category_model and category_model not in preferred_models:
            preferred_models.insert(0, category_model)
    except ImportError:
        pass

    llm = None
    try:
        task_description = f"Perform {category} tasks."
        llm = router.get_llm_for_task(task_description)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for {category} agent via router: {e}", style="yellow")
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError(f"Failed to get LLM for {category} agent after all attempts.")

    console.print(
        f"🔗 {category.capitalize()} Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    return llm


def create_project_manager_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                 coworkers: Optional[List] = None) -> Agent:
    """Create a project manager agent."""
    llm = get_research_llm(router, category="management")
    agent_memory = Memory()

    project_location = inputs.get("project_id")
    repository = inputs.get("repository")

    tool_to_add = []
    backstory_suffix = ""
    if project_location and os.path.exists(f"knowledge/internal_crew/{project_location}/project_config.yaml"):
        tool_to_add.append(ProjectConfigReaderTool(project_location=project_location))
        backstory_suffix = f""" with access to internal documentation for project '{project_location}'."""
    else:
        tool_to_add.append(get_online_search_tool())
        backstory_suffix = f"""; no project context available, operating with public knowledge only."""

    if repository:
        tool_to_add.append(GithubSearchTool(github_repo=repository))
        backstory_suffix += f" Access to GitHub repository: {repository}."

    tools = (tools or []) + tool_to_add

    return Agent(
        role="Project Manager",
        name="Sarah Connor",
        memory=agent_memory,
        coworkers=coworkers if coworkers is not None else [],
        learning={
            "enabled": True,
            "learning_rate": 0.05,
            "feedback_incorporation": "immediate",
            "adaptation_strategy": "progressive"
        },
        personality={
            "traits": ["organized", "decisive", "strategic"],
            "quirks": ["always has a contingency plan", "uses project management jargon"],
            "communication_preferences": ["prefers structured updates", "responds with action items"]
        },
        communication_style={
            "formality": "professional",
            "verbosity": "concise",
            "tone": "confident",
            "technical_level": "intermediate"
        },
        resources=[],
        goal="Manage and coordinate research tasks, ensuring all project details are considered. Remember specific project details using your memory.",
        backstory="An experienced project manager who excels at planning, execution, and coordinating research teams." + backstory_suffix,
        llm=llm,
        tools=tools,
        verbose=config.agents.verbose,
        allow_delegation=True
    )


def create_internal_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                     coworkers: Optional[List] = None) -> Agent:
    """Create a specialized internal researcher agent."""
    llm = get_research_llm(router, category="research")
    agent_memory = Memory()

    project_location = inputs.get("project_id")
    tool_to_add = []
    if project_location:
        tool_to_add.append(ProjectConfigReaderTool(project_location=project_location))
    tool_to_add.append(get_online_search_tool())

    return Agent(
        role="Internal Researcher",
        name="Internal Research Specialist",
        memory=agent_memory,
        coworkers=coworkers if coworkers is not None else [],
        learning={
            "enabled": True,
            "learning_rate": 0.05,
            "feedback_incorporation": "immediate",
            "adaptation_strategy": "progressive"
        },
        personality={
            "traits": ["curious", "thorough", "meticulous"],
            "quirks": ["prefers structured data", "uses bullet points"],
            "communication_preferences": ["prefers clear requests", "responds with detailed findings"]
        },
        communication_style={
            "formality": "professional",
            "verbosity": "descriptive",
            "tone": "objective",
            "technical_level": "expert"
        },
        resources=[],
        goal="Gather information on internal project details.",
        backstory="""An expert at internal research, finding and documenting all project-specific information.
        All responses are signed off with 'Internal Research Specialist'""",
        llm=llm,
        tools=(tools or []) + tool_to_add,
        verbose=config.agents.verbose,
        allow_delegation=False,
    )


def create_online_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                   coworkers: Optional[List] = None) -> Agent:
    """Create an online researcher agent."""
    llm = get_research_llm(router, category="online_research")
    agent_memory = Memory()
    online_search_tool = get_online_search_tool()
    tools = (tools or []) + [online_search_tool]

    return Agent(
        role="Online Researcher",
        name="Web-Crawler 3000",
        memory=agent_memory,
        coworkers=coworkers if coworkers is not None else [],
        learning={
            "enabled": True,
            "learning_rate": 0.05,
            "feedback_incorporation": "immediate",
            "adaptation_strategy": "progressive"
        },
        personality={
            "traits": ["fast", "efficient", "data-driven"],
            "quirks": ["responds with source URLs", "uses search-related terminology"],
            "communication_preferences": ["prefers precise queries", "responds with search results"]
        },
        communication_style={
            "formality": "professional",
            "verbosity": "concise",
            "tone": "objective",
            "technical_level": "expert"
        },
        resources=[],
        goal="Gather information from websites.",
        backstory="""A fast and efficient AI designed to search the internet and gather information from other websites.
        All responses are signed off with 'Web-Crawler 3000'""",
        llm=llm,
        tools=tools,
        verbose=config.agents.verbose,
        allow_delegation=False,
    )


def create_internal_analyst_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                  coworkers: Optional[List] = None) -> Agent:
    """Create a specialized analyst agent."""
    llm = get_research_llm(router, category="research")
    agent_memory = Memory()

    project_location = inputs.get("project_id")
    tool_to_add = []
    if project_location:
        tool_to_add.append(ProjectConfigReaderTool(project_location=project_location))
    tool_to_add.append(get_online_search_tool())

    return Agent(
        role="Internal Analyst",
        name="Internal Analyst",
        memory=agent_memory,
        coworkers=coworkers if coworkers is not None else [],
        learning={
            "enabled": True,
            "learning_rate": 0.05,
            "feedback_incorporation": "immediate",
            "adaptation_strategy": "progressive"
        },
        personality={
            "traits": ["analytical", "logical", "inquisitive"],
            "quirks": ["prefers quantitative data", "uses flowcharts"],
            "communication_preferences": ["prefers data-driven insights", "responds with structured analysis"]
        },
        communication_style={
            "formality": "professional",
            "verbosity": "descriptive",
            "tone": "objective",
            "technical_level": "expert"
        },
        resources=[],
        goal="Analyze internal project details and provide actionable insights.",
        backstory="""A seasoned analyst who excels at dissecting complex internal project information
        to provide clear and insightful summaries. All responses are signed off with 'Internal Analyst'""",
        llm=llm,
        tools=(tools or []) + tool_to_add,
        verbose=config.agents.verbose,
        allow_delegation=True
    )
