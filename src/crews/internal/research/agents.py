# src/crews/internal/research/agents.py

import inspect
import importlib
from crewai import Agent
from crewai.tools import BaseTool # FIX: Import BaseTool
from typing import Dict, Any, List, Optional
from distributed_router import DistributedRouter
from config import config
from rich.console import Console
from src.utils.memory import Memory

# Assume SerperDevTool would be used for OnlineSearchTool
# from crewai_tools.tools import SerperDevTool

console = Console()

# FIX: Refactor custom tools to inherit from BaseTool
class ProjectConfigReaderTool(BaseTool):
    name: str = "Project Config Reader"
    description: str = "Reads project details from a YAML file based on the project location."

    def __init__(self, project_location):
        self.project_location = project_location
        # The __init__ in BaseTool handles registration and schema setup automatically.

    def _run(self, *args, **kwargs):
        # Implementation to read the YAML file based on self.project_location
        # This is where your original `read_config` logic goes
        pass


# FIX: Refactor custom tools to inherit from BaseTool
class OnlineSearchTool(BaseTool):
    name: str = "Online Search"
    description: str = "Performs online searches to find information from websites."

    def __init__(self):
        # Implementation of online search using a search API (e.g., SerperDevTool)
        pass

    def _run(self, query: str):
        # Implementation of online search using a search API
        # This is where your original `online_search` logic goes
        pass


def get_research_llm(router: DistributedRouter, category: str = "research",
                     preferred_models: Optional[List] = None) -> Any:
    # ... (no changes needed here) ...
    pass


def create_project_manager_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                 coworkers: Optional[List] = None) -> Agent:
    # ... (existing agent creation logic) ...
    return Agent(
        # ... (rest of the agent properties) ...
        llm=llm,
        tools=tools,
        verbose=config.agents.verbose,
        allow_delegation=True
    )


def create_internal_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                     coworkers: Optional[List] = None) -> Agent:
    # ... (existing agent creation logic) ...
    return Agent(
        # ... (rest of the agent properties) ...
        llm=llm,
        tools=tools,
        verbose=config.agents.verbose,
        allow_delegation=False,
    )


def create_online_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                   coworkers: Optional[List] = None) -> Agent:
    """Create an online researcher agent."""
    llm = get_research_llm(router, category="online_research")
    agent_memory = Memory()
    online_search_tool = OnlineSearchTool()
    tools = tools + [online_search_tool] if tools else [online_search_tool]

    return Agent(
        # ... (rest of the agent properties) ...
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
        tools=tools,
        verbose=config.agents.verbose,
        allow_delegation=True
    )
