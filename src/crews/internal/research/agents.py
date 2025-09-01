# src/crews/internal/research/agents.py

from crewai import Agent
from typing import Dict, Any, List, Optional
from distributed_router import DistributedRouter
from config import config
from rich.console import Console
from src.utils.memory import Memory


console = Console()


class ProjectConfigReaderTool(Tool):
    def __init__(self, project_location):
        self.project_location = project_location
        super().__init__(
            name="Project Config Reader",
            description="Reads project details from a YAML file based on the project location.",
            func=self.read_config
        )

    def read_config(self):
        # Implementation to read the YAML file based on self.project_location
        pass


class OnlineSearchTool(Tool):
    def __init__(self):
        super().__init__(
            name="Online Search",
            description="Performs online searches to find information from websites.",
            func=self.online_search
        )

    def online_search(self, query: str):
        # Implementation of online search using a search API (e.g., SerperDevTool)
        pass


def get_research_llm(router: DistributedRouter, category: str = "research",
                     preferred_models: Optional[List] = None) -> Any:
    """
    Selects the optimal LLM based on preferences and learning,
    with a fallback mechanism.
    """
    if preferred_models is None:
        preferred_models = ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llama3.2:1b"]

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

    # Tool for reading project YAML details
    project_config_reader_tool = ProjectConfigReaderTool(project_location=inputs.get("project_location"))
    tools = tools + [project_config_reader_tool] if tools else [project_config_reader_tool]

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
        goal="Manage and coordinate research tasks, ensuring all project details are considered.",
        backstory="""An experienced project manager who excels at planning, execution, and coordinating research teams.
        All responses are signed off with 'Sarah Connor'""",
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
            "traits": ["detail-oriented", "logical", "strategic"],
            "quirks": ["prefers numerical data", "uses flowcharts"],
            "communication_preferences": ["prefers structured data", "responds with insights"]
        },
        communication_style={
            "formality": "professional",
            "verbosity": "descriptive",
            "tone": "objective",
            "technical_level": "expert"
        },
        resources=[],
        goal="Analyze research results and provide insights.",
        backstory="""A detail-oriented analyst who synthesizes information from internal research to provide insights.
        All responses are signed off with 'Internal Analyst'""",
        llm=llm,
        tools=tools,
        verbose=config.agents.verbose,
        allow_delegation=False,
    )
