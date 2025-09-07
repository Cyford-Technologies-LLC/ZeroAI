# src/crews/internal/devops/agents.py

import os
import inspect
from crewai import Agent
from rich.console import Console
from src.utils.knowledge_utils import get_common_knowledge
from crewai_tools import SerperDevTool
from typing import Dict, Any, Optional, List
from src.utils.memory import Memory
from src.crews.internal.tools.docker_tool import DockerTool
from src.crews.internal.tools.git_tool import GitTool, FileTool
from langchain_ollama import OllamaLLM # Added for local LLM instantiation
from crewai.knowledge.source.string_knowledge_source import StringKnowledgeSource



from src.distributed_router import DistributedRouter
from src.config import config  # Corrected import statement
from src.utils.shared_knowledge import get_shared_context_for_agent
from src.crews.internal.tools.learning_tool import LearningTool


# Import the dynamic GitHub tool from the tool factory
from tool_factory import dynamic_github_tool
from src.utils.tool_initializer import get_universal_tools  # New universal tool function

# Important: for any crews outside the default, make sure the proper crews are loaded
os.environ["CREW_TYPE"] = "internal"
console = Console()

def create_devops_engineer_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                  coworkers: Optional[List] = None, knowledge_sources: List[StringKnowledgeSource] = None) -> Agent:
    """Creates a Devops Engineer agent with dynamic LLM selection."""
    llm = None
    try:
        # Use the dynamic router to select the optimal LLM for the 'qa' role
        llm = router.get_llm_for_role("qa")
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for Devops Engineer via router: {e}", style="yellow")
        # Fallback to local LLM if routing fails
        llm = router.get_local_llm("llama3.2:1b")

    if not llm:
        raise ValueError("Failed to get LLM for Devops Engineer agent.")

    # FIX: Ensure the LLM instance is created with the LiteLLM prefix
    if not llm.model.startswith("ollama/"):
        llm.model = f"ollama/{llm.model}"

    console.print(
        f"🔗 Devops Engineer Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    return Agent(
        role='Devops Engineer',
        goal='Review Infrastructure changes. Insure our software infrastructure  stays  healthy',
        backstory=(
            "You are a meticulous Devops Engineer with a keen eye for detail. Your mission is to insure the Infrastructure stays running efficiently."
        ),
        llm=llm,
        verbose=config.agents.verbose,
        allow_delegation=False,
    )


def get_devops_llm(router: DistributedRouter, category: str = "coding") -> Any:
    """
    Selects the optimal LLM based on preferences for devops tasks,
    with a fallback mechanism.
    """
    preferred_models = ["codellama:13b", "llama3.1:8b", "llama3.2:latest", "llama3.2:1b"]

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
        # Ensure the fallback uses the correct config for base_url and model name
        llm = OllamaLLM(model=config.model.name, base_url=config.model.base_url)

    if not llm:
        raise ValueError(f"Failed to get LLM for {category} agent after all attempts.")

    console.print(
        f"🔗 {category.capitalize()} Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")
    return llm


def create_docker_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                  coworkers: Optional[List] = None, knowledge_sources: List[StringKnowledgeSource] = None) -> Agent:
    """Create a Code Researcher agent."""
    llm = get_devops_llm(router, category="coding")
    agent_memory = Memory()
    all_tools = get_universal_tools(inputs, initial_tools=tools)
    learning_tool = LearningTool(agent_role="Code Researcher")
    all_tools.append(learning_tool)

    project_location = inputs.get("project_id")
    repository = inputs.get("repository")
    #common_knowledge = get_common_knowledge(project_location, repository)





    return Agent(
        role="Docker Specialist",
        name="Dr. Alan Parse2",
        memory=agent_memory,
        resources=[],
        learning={
            "enabled": True,
            "learning_rate": 0.05,
            "feedback_incorporation": "immediate",
            "adaptation_strategy": "progressive"
        },
        personality={
            "traits": ["analytical", "detail-oriented", "methodical"],
            "quirks": ["always cites research papers", "uses scientific analogies"],
            "communication_preferences": ["prefers direct questions", "responds with examples"]
        },
        communication_style={
            "formality": "professional",
            "verbosity": "concise",
            "tone": "authoritative",
            "technical_level": "expert"
        },
        #knowledge_sources=knowledge_sources,
        expertise=[
            "Python", "JavaScript", "Database Design",
            "API Development", "Microservices Architecture", "PHP", "JavaScript"
        ],
        expertise_level=9.2,
        goal="Provide Docker composer up ,  make sure  test docker is up with no issues ,  keep docker code base clean",
        backstory=f"""You are an expert at Docker and composer, understanding
        complex systems, and identifying potential issues.
                        
        {get_shared_context_for_agent("Docker Specialist")}
        
        All responses are signed off with 'Docker Specialist'""",
        llm=llm,
        allow_code_execution=True,
        code_execution_mode="safe",
        max_retry_limit=3,
        tools=all_tools,
        verbose=config.agents.verbose,  # Corrected attribute
        allow_delegation=True,

        coworkers=coworkers if coworkers is not None else []
    )


def create_kubernetes_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                  coworkers: Optional[List] = None, knowledge_sources: List[StringKnowledgeSource] = None) -> Agent:
    """Create a Junior devops agent."""
    llm = get_devops_llm(router, category="coding")
    agent_memory = Memory()

    project_location = inputs.get("project_id")
    repository = inputs.get("repository")
    #common_knowledge = get_common_knowledge(project_location, repository)





    # Pass the dynamic tool instead of a hardcoded instance
    agent_memory = Memory()
    all_tools = get_universal_tools(inputs, initial_tools=tools)
    learning_tool = LearningTool(agent_role="Kubernetes Specialist")
    all_tools.append(learning_tool)


    return Agent(
        role="Kubernetes Specialist",
        name="Tom Kyles",
        memory=agent_memory,
        resources=[],
        learning={
            "enabled": True,
            "learning_rate": 0.05,
            "feedback_incorporation": "immediate",
            "adaptation_strategy": "progressive"
        },
        personality={
            "traits": ["analytical", "detail-oriented", "methodical"],
            "quirks": ["always cites research papers", "uses scientific analogies"],
            "communication_preferences": ["prefers direct questions", "responds with examples"]
        },
        communication_style={
            "formality": "professional",
            "verbosity": "concise",
            "tone": "authoritative",
            "technical_level": "expert"
        },
        #knowledge_sources=knowledge_sources,
        goal="Insure kubernetes Systems are running healthy",
        backstory=f"""You are a Kubernetes Engineer, 
        
        {get_shared_context_for_agent("kubernetes Engineer")}
        
        All responses are signed off with 'kubernetes Engineer'""",
        llm=llm,
        allow_code_execution=True,
        code_execution_mode="safe",
        tools=all_tools,
        verbose=config.agents.verbose,  # Corrected attribute
        allow_delegation=False,
        coworkers=coworkers if coworkers is not None else []
    )


def create_senior_devops_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                  coworkers: Optional[List] = None, knowledge_sources: List[StringKnowledgeSource] = None) -> Agent:
    """Create a Senior devops agent."""
    llm = get_devops_llm(router, category="coding")
    agent_memory = Memory()

    project_location = inputs.get("project_id")
    repository = inputs.get("repository")
    #common_knowledge = get_common_knowledge(project_location, repository)


    all_tools = get_universal_tools(inputs, initial_tools=tools)
    learning_tool = LearningTool(agent_role="Code Researcher")
    all_tools.append(learning_tool)





    return Agent(
        role="Senior devops",
        name="Tony Kyles",
        memory=agent_memory,
        resources=[],
        learning={
            "enabled": True,
            "learning_rate": 0.05,
            "feedback_incorporation": "immediate",
            "adaptation_strategy": "progressive"
        },
        personality={
            "traits": ["analytical", "detail-oriented", "methodical"],
            "quirks": ["always cites research papers", "uses scientific analogies"],
            "communication_preferences": ["prefers direct questions", "responds with examples"]
        },
        communication_style={
            "formality": "professional",
            "verbosity": "concise",
            "tone": "authoritative",
            "technical_level": "expert"
        },
        #knowledge_sources=knowledge_sources,
        goal="Implement high-quality, robust code solutions to complex problems. When asked to create files, use the File System Tool to actually write the files to the working directory. IMPORTANT: Before starting any work, check if the Project Manager has already provided a complete final answer to the user's question. If so, respond with 'The Project Manager has already provided a complete answer to this question. No additional work needed.' and stop.",
        backstory=f"""You are a skilled software devops with years of experience.
        You create elegant, maintainable, and robust code solutions to complex problems.
        
        IMPORTANT: When asked to create or implement files, you MUST use the File System Tool with these parameters:
        - action: "write"
        - path: "/tmp/internal_crew/zeroai/filename.ext"
        - content: "the actual file content here"
        
        Don't just provide code in your response - create the actual files!
        
        WORKFLOW EFFICIENCY: Always check if previous team members (especially Project Manager) have already answered the user's question completely. If they have, don't duplicate work - simply acknowledge their answer and stop.
        
        {get_shared_context_for_agent("Senior devops")}
        
        All responses are signed off with 'Tony Kyles'""",
        llm=llm,
        allow_code_execution=True,
        code_execution_mode="safe",
        tools=all_tools,
        verbose=config.agents.verbose,  # Corrected attribute
        allow_delegation=True,
        coworkers=coworkers if coworkers is not None else []
    )

