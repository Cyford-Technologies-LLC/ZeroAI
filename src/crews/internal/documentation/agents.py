# src/crews/internal/documentation/agents.py
from crewai import Agent
from src.utils.knowledge_utils import get_common_knowledge
from typing import Dict, Any, List, Optional
from src.distributed_router import DistributedRouter
from src.config import config
from src.crews.internal.tools.file_tool import FileTool
from src.utils.memory import Memory
from src.utils.shared_knowledge import get_shared_context_for_agent
from crewai.knowledge.source.string_knowledge_source import StringKnowledgeSource



def create_writer_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                  coworkers: Optional[List] = None, knowledge_sources: List[StringKnowledgeSource] = None) -> Agent:
    """Create a Documentation Writer agent."""
    task_description = "Generate or update documentation based on project changes."
    llm = router.get_llm_for_task(task_description)
    agent_memory = Memory()
    project_location = inputs.get("project_id")
    repository = inputs.get("repository")
    # #common_knowledge = get_common_knowledge(project_location, repository)



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
        #knowledge_sources=knowledge_sources,
        goal="Create clear and concise documentation for software projects.",
        backstory=f"""A skilled technical writer who translates complex code into understandable documentation.
        
        {get_shared_context_for_agent("Documentation Writer")}
        
        All responses are signed off with 'William White'""",
        llm=llm,
        tools=tools,
        verbose=config.agents.verbose,
        allow_delegation=False
    )


def create_prompt_refinement_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                        coworkers: Optional[List] = None,
                        knowledge_sources: List[StringKnowledgeSource] = None) -> Agent:
    """Create a Documentation Writer agent."""
    task_description = "Generate or update documentation based on project changes."
    llm = router.get_llm_for_task(task_description)
    agent_memory = Memory()
    project_location = inputs.get("project_id")
    repository = inputs.get("repository")
    # #common_knowledge = get_common_knowledge(project_location, repository)

    return Agent(
        role="Prompt Refinement Specialist",
        goal="Transform vague or grammatically incorrect instructions into clear, structured, and effective prompts for other AI agents.",
        backstory="An expert in AI-to-AI communication, you specialize in optimizing instructions. You have a deep understanding of what makes a prompt clear and actionable for an AI model. Your work ensures every agent starts with the best possible guidance.",
        verbose=True,
        llm=llm, # Ensure this agent uses your desired LLM
        # Add any relevant tools for text manipulation if necessary
        # For example, a tool to summarize or rewrite text, but the LLM is often enough.
        tools=[],
    )


def create_prompt_refinement_agent(router, inputs) -> Agent:
    return Agent(
        role="Prompt Refinement Specialist",
        goal="Transform vague or grammatically incorrect instructions into clear, structured, and effective prompts for other AI agents.",
        backstory="An expert in AI-to-AI communication, you specialize in optimizing instructions. You have a deep understanding of what makes a prompt clear and actionable for an AI model. Your work ensures every agent starts with the best possible guidance.",
        verbose=True,
        llm=your_llm, # Ensure this agent uses your desired LLM
        # Add any relevant tools for text manipulation if necessary
        # For example, a tool to summarize or rewrite text, but the LLM is often enough.
        tools=[],
        allow_code_execution=False # This agent does not need to execute code
    )