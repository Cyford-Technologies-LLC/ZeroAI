# src/crews/internal/research/agents.py

import inspect
import importlib
from crewai import Agent
from crewai.tools import BaseTool
from typing import Dict, Any, List, Optional, Any as AnyType
from src.distributed_router import DistributedRouter
from src.config import config
from src.utils.shared_knowledge import get_shared_context_for_agent
from rich.console import Console
from src.utils.memory import Memory
from pathlib import Path
import os
import yaml
from crewai_tools import SerperDevTool, GithubSearchTool
from langchain_ollama import OllamaLLM


class DelegationTool(BaseTool):
    name: str = "Delegate work to coworker"
    description: str = "Delegate a specific task to one of the following coworkers: Documentation Writer, CrewAI Diagnostic Agent, Scheduler, Code Researcher, Senior Developer, QA Engineer, Git Operator, Internal Researcher, Online Researcher, Junior Developer, QA Engineer, Senior Developer\nThe input to this tool should be the coworker, the task you want them to do, and ALL necessary context to execute the task, they know nothing about the task, so share absolutely everything you know, don't reference things but instead explain them."
    coworkers: List[Agent]
    
    def __init__(self, coworkers: List[Agent]):
        super().__init__(coworkers=coworkers)
    
    def _run(self, task: str, context: str, coworker: str) -> str:
        """Delegate a task to a specific coworker."""
        target_coworker = None
        for c in self.coworkers:
            if c.role == coworker:
                target_coworker = c
                break
        
        if not target_coworker:
            available_roles = [c.role for c in self.coworkers]
            return f"Coworker '{coworker}' not found. Available coworkers: {', '.join(available_roles)}"
        
        try:
            full_task = f"Task: {task}\nContext: {context}"
            response = target_coworker.execute_task(full_task)
            return f"Response from {coworker}: {response}"
        except Exception as e:
            return f"Error delegating to {coworker}: {str(e)}"


class QuestionTool(BaseTool):
    name: str = "Ask question to coworker"
    description: str = "Ask a specific question to one of the following coworkers: Documentation Writer, CrewAI Diagnostic Agent, Scheduler, Code Researcher, Senior Developer, QA Engineer, Git Operator, Internal Researcher, Online Researcher, Junior Developer, QA Engineer, Senior Developer\nThe input to this tool should be the coworker, the question you have for them, and ALL necessary context to ask the question properly, they know nothing about the question, so share absolutely everything you know, don't reference things but instead explain them."
    coworkers: List[Agent]
    
    def __init__(self, coworkers: List[Agent]):
        super().__init__(coworkers=coworkers)
    
    def _run(self, question: str, context: str, coworker: str) -> str:
        """Ask a question to a specific coworker."""
        target_coworker = None
        for c in self.coworkers:
            if c.role == coworker:
                target_coworker = c
                break
        
        if not target_coworker:
            available_roles = [c.role for c in self.coworkers]
            return f"Coworker '{coworker}' not found. Available coworkers: {', '.join(available_roles)}"
        
        try:
            full_question = f"Question: {question}\nContext: {context}"
            response = target_coworker.execute_task(full_question)
            return f"Response from {coworker}: {response}"
        except Exception as e:
            return f"Error asking {coworker}: {str(e)}"

# NOTE: Import tool_factory with error handling
try:
    from tool_factory import dynamic_github_tool
    TOOL_FACTORY_AVAILABLE = True
except ImportError as e:
    console = Console()
    console.print(f"⚠️ Warning: Could not import tool_factory: {e}", style="yellow")
    TOOL_FACTORY_AVAILABLE = False
    dynamic_github_tool = None

# NOTE: Import with error handling for missing tool_initializer
try:
    from src.utils.tool_initializer import get_universal_tools
except ImportError as e:
    console = Console()
    console.print(f"⚠️ Warning: Could not import get_universal_tools: {e}", style="yellow")
    # Fallback function if import fails
    def get_universal_tools(inputs, initial_tools=None):
        """Fallback function when tool_initializer is not available"""
        return initial_tools or []

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

    def _run(self, query: str):
        try:
            # NOTE: SerperDevTool requires SERPER_API_KEY environment variable
            search_tool = SerperDevTool()
            return search_tool.run(query)
        except Exception as e:
            # NOTE: Fallback when SERPER_API_KEY is not available
            return f"Online search not available (API key missing): {str(e)}. Please provide search results manually or set SERPER_API_KEY environment variable."


def get_online_search_tool():
    """Helper function to get a configured online search tool."""
    return OnlineSearchTool()


def get_research_llm(router: DistributedRouter, category: str = "research",
                     preferred_models: Optional[List] = None) -> AnyType:
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
        # Ensure the fallback uses the correct config for base_url and model name
        llm = router.get_local_llm(model_name=config.model.name, base_url=config.model.base_url)

    if not llm:
        raise ValueError(f"Failed to get LLM for {category} agent after all attempts.")

    console.print(
        f"🔗 {category.capitalize()} Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    return llm


def _get_tools_with_github(inputs: Dict[str, Any], tools: Optional[List] = None) -> List:
    """Helper function to get tools including dynamic_github_tool when available"""
    base_tools = tools or []
    
    # Add configured GitHub tool if available
    if TOOL_FACTORY_AVAILABLE and dynamic_github_tool:
        repo_token_key = inputs.get("repo_token_key")
        if repo_token_key:
            console.print(f"🔧 Configuring GitHub tool with token key: {repo_token_key}", style="green")
            # Create a configured version that uses the specific token key
            class ConfiguredGithubTool(dynamic_github_tool.__class__):
                name: str = "Dynamic GitHub Search Tool"
                description: str = "Searches a specific GitHub repository using the correct token based on a provided key."
                
                def _run(self, repo_name: str, token_key: Optional[str] = None, query: str = "") -> str:
                    # Always use the configured token key, ignore what agent passes
                    console.print(f"🔧 GitHub tool using configured token key: {repo_token_key} (agent passed: {token_key})", style="dim")
                    return super()._run(repo_name, repo_token_key, query)
            
            configured_tool = ConfiguredGithubTool()
            # Replace the original tool completely
            base_tools = [tool for tool in base_tools if not (hasattr(tool, 'name') and 'GitHub' in tool.name)]
            base_tools.append(configured_tool)
        else:
            console.print(f"⚠️ No repo_token_key found in inputs: {list(inputs.keys())}", style="yellow")
            base_tools = base_tools + [dynamic_github_tool]
    
    # Use get_universal_tools with fallback handling but exclude duplicate GitHub tools
    try:
        all_tools = get_universal_tools(inputs, initial_tools=base_tools)
        # Remove any duplicate GitHub tools from get_universal_tools
        github_tools = [tool for tool in all_tools if hasattr(tool, 'name') and 'GitHub' in tool.name]
        if len(github_tools) > 1:
            console.print(f"🔧 Removing {len(github_tools)-1} duplicate GitHub tools", style="yellow")
            # Keep only the first GitHub tool (our configured one)
            all_tools = [tool for tool in all_tools if not (hasattr(tool, 'name') and 'GitHub' in tool.name)] + [github_tools[0]]
    except Exception as e:
        console.print(f"⚠️ Warning: get_universal_tools failed: {e}", style="yellow")
        all_tools = base_tools
    
    return all_tools


def create_project_manager_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                 coworkers: Optional[List] = None, backstory_suffix=None) -> Agent:
    """Create a project manager agent."""
    llm = get_research_llm(router, category="management")

    project_location = inputs.get("project_id")
    repository = inputs.get("repository")

    all_tools = _get_tools_with_github(inputs, tools)
    
    # Add delegation tools if coworkers are provided
    if coworkers:
        delegation_tool = DelegationTool(coworkers=coworkers)
        question_tool = QuestionTool(coworkers=coworkers)
        all_tools.extend([delegation_tool, question_tool])
        console.print(f"🔧 Added delegation tools to Project Manager with {len(coworkers)} coworkers", style="green")

    return Agent(
        role="Project Manager",
        name="Sarah Connor",
        memory=True,
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
        goal="Answer user questions directly using existing knowledge. Only use tools when you genuinely don't know the answer. "
             "DIRECT ANSWERS FIRST: For simple questions, provide direct answers from your knowledge without using tools. "
             "KNOWN INFORMATION: The project repository is https://github.com/Cyford-Technologies-LLC/ZeroAI.git - use this for git URL questions. "
             f"MEMORY PRIORITY: Check your memory first. Only read knowledge/internal_crew/{project_location}/project_config.yaml if you need specific project details you don't already know. "
             "TOOL USAGE: Only use tools when you genuinely need new information you don't have. "
             "CRITICAL: Provide conversational, human-readable answers. Never return raw YAML, JSON, or file contents. "
             "If you don't know something, say 'I don't have that information' - never make up details.",
        backstory=f"An experienced project manager who excels at planning, execution, and coordinating research teams.{backstory_suffix or ''}\n\n{get_shared_context_for_agent('Project Manager')}\n\nAll responses are signed off with 'Sarah Connor'",
        llm=llm,
        tools=all_tools,
        verbose=config.agents.verbose,
        allow_delegation=True
    )


def create_internal_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                     coworkers: Optional[List] = None) -> Agent:
    """Create a specialized internal researcher agent."""
    llm = get_research_llm(router, category="research")
    agent_memory = Memory()
    project_location = inputs.get("project_id")
    
    all_tools = _get_tools_with_github(inputs, tools)

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
        backstory=f"""An expert at internal research, finding and documenting all project-specific information.
        
        {get_shared_context_for_agent("Internal Researcher")}
        
        All responses are signed off with 'Internal Research Specialist'""",
        llm=llm,
        tools=all_tools,
        verbose=config.agents.verbose,
        allow_delegation=False,
    )


def create_online_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                   coworkers: Optional[List] = None) -> Agent:
    """Create an online researcher agent."""
    llm = get_research_llm(router, category="online_research")
    agent_memory = Memory()
    
    all_tools = _get_tools_with_github(inputs, tools)

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
            "tone": "confident",
            "technical_level": "intermediate"
        },
        resources=[],
        goal="Perform comprehensive online searches to find information.",
        backstory=f"""A specialized agent for efficient online information retrieval.
        
        {get_shared_context_for_agent("Online Researcher")}
        
        All responses are signed off with 'Web-Crawler 3000'""",
        llm=llm,
        tools=all_tools,
        verbose=config.agents.verbose,
        allow_delegation=False
    )