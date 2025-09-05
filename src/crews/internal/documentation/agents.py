# src/crews/internal/documentation/agents.py
from crewai import Agent
# from crewai_tools import DirectoryKnowledgeSource, StringKnowledgeSource
from typing import Dict, Any, List, Optional
from src.distributed_router import DistributedRouter
from src.config import config
from src.crews.internal.tools.file_tool import FileTool
from src.utils.memory import Memory
from src.utils.shared_knowledge import get_shared_context_for_agent

def create_writer_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None, coworkers: Optional[List] = None) -> Agent:
    """Create a Documentation Writer agent."""
    task_description = "Generate or update documentation based on project changes."
    llm = router.get_llm_for_task(task_description)
    agent_memory = Memory()
    project_location = inputs.get("project_id")
    repository = inputs.get("repository")
    project_knowledge = DirectoryKnowledgeSource(
        directory=f"knowledge/internal_crew/{project_location}"
    ),
    repo_knowledge = StringKnowledgeSource(
        content=f"The project's Git repository is located at: {repository}"
    )


    return Agent(
        role="Documentation Writer",
        name="William White",
        memory=agent_memory,
        coworkers=coworkers if coworkers is not None else [],
        learning={
                "enabled": True,
                "learning_rate": 0.05,
                "feedback_incorporation": "immediate",
                "adaptation_strategy": "progressive"
            },
        personality={
                "traits": ["analytical", "detail-oriented", "methodical", "collaborative"],
                "quirks": ["prefers clear instructions", "uses bullet points extensively"],
                "communication_preferences": ["prefers direct questions", "responds with structured examples"]
            },
        communication_style={
                "formality": "professional",
                "verbosity": "descriptive",
                "tone": "cooperative",
                "technical_level": "expert"
            },
        resources=[],
        knowledge_sources=[
            project_knowledge,  # This points to the local directory
            repo_knowledge  # This provides the agent with the repository URL
        ],
        goal="Create clear and concise documentation for software projects.",
        backstory=f"""A skilled technical writer who translates complex code into understandable documentation.
        
        {get_shared_context_for_agent("Documentation Writer")}
        
        All responses are signed off with 'William White'""",
        llm=llm,
        tools=tools,
        verbose=config.agents.verbose,
        allow_delegation=False
    )
