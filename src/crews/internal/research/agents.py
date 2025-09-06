import inspect
import importlib
from crewai import Agent
from src.utils.knowledge_utils import get_common_knowledge
from crewai_tools import SerperDevTool
from crewai.tools import BaseTool
from typing import Dict, Any, List, Optional, Any as AnyType
from crewai.knowledge.source.string_knowledge_source import StringKnowledgeSource
from crewai import Agent, Knowledge

from openai import resources

from src.distributed_router import DistributedRouter
from src.config import config
from src.utils.shared_knowledge import get_shared_context_for_agent, get_agent_learning_path , save_agent_learning  ,get_agent_learning_path , load_team_briefing


from rich.console import Console
from src.utils.memory import Memory
from pathlib import Path
import os
import yaml

console = Console()

# Placeholder for Ollama configuration
ollama_embedder_config = {
    "provider": "ollama",
    "config": {
        "model": "mxbai-embed-large",
        "base_url": "http://149.36.1.65:11434"
    }
}


class DelegationTool(BaseTool):
    name: str = "Ask question to coworker"
    description: str = "Delegate a task or question to a specific coworker by their exact role name."
    coworkers: List[Agent]

    def __init__(self, coworkers: List[Agent]):
        super().__init__(coworkers=coworkers)

    def _run(self, coworker_role: str, question: str) -> str:
        """Delegate a question to a specific coworker."""
        target_coworker = next((c for c in self.coworkers if c.role == coworker_role), None)

        if not target_coworker:
            available_roles = [c.role for c in self.coworkers]
            return f"Coworker '{coworker_role}' not found. Available coworkers: {', '.join(available_roles)}"

        try:
            response = target_coworker.execute_task(question)
            return f"Response from {coworker_role}: {response}"
        except Exception as e:
            return f"Error delegating to {coworker_role}: {str(e)}"


# NOTE: Import tool_factory with error handling
try:
    from tool_factory import dynamic_github_tool

    TOOL_FACTORY_AVAILABLE = True
except ImportError as e:
    console.print(f"⚠️ Warning: Could not import tool_factory: {e}", style="yellow")
    TOOL_FACTORY_AVAILABLE = False
    dynamic_github_tool = None

# NOTE: Import with error handling for missing tool_initializer
try:
    from src.utils.tool_initializer import get_universal_tools
except ImportError as e:
    console.print(f"⚠️ Warning: Could not import get_universal_tools: {e}", style="yellow")


    # Fallback function if import fails
    def get_universal_tools(inputs, initial_tools=None):
        """Fallback function when tool_initializer is not available"""
        return initial_tools or []


class ProjectTool(BaseTool):
    name: str = "Project Tool"
    description: str = "Get project information. Use 'all' to get full config, 'file' to get file path, or specify a key like 'repository.url' or 'project.name'."

    def _run(self, project_location: str, mode: str) -> str:
        config_path = Path("knowledge") / "internal_crew" / project_location / "project_config.yaml"

        if not config_path.is_file():
            base_path = Path("knowledge") / "internal_crew"
            for item in base_path.rglob("project_config.yaml"):
                if project_location.lower() in str(item).lower():
                    config_path = item
                    break

        if mode == "file":
            return str(config_path)

        if not config_path.is_file():
            return f"Error: No project configuration found for '{project_location}'. Searched in knowledge/internal_crew/"

        with open(config_path, 'r') as f:
            project_config = yaml.safe_load(f) or {}

        if mode == "all":
            return yaml.dump(project_config, default_flow_style=False)

        if '.' in mode:
            key = mode
            value = project_config
            for k in key.split('.'):
                if isinstance(value, dict) and k in value:
                    value = value[k]
                else:
                    return f"Key '{key}' not found in project config."
            return str(value)

        return "Specify mode: 'all', 'file', or provide a key like 'repository.url'"


class OnlineSearchTool(BaseTool):
    name: str = "Online Search"
    description: str = "Performs online searches to find information from websites."

    def _run(self, query: str):
        try:
            search_tool = SerperDevTool()
            return search_tool.run(query)
        except Exception as e:
            return f"Online search not available (API key missing): {str(e)}. Please provide search results manually or set SERPER_API_KEY environment variable."


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

    try:
        task_description = f"Perform {category} tasks."
        llm = router.get_llm_for_task(task_description)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for {category} agent via router: {e}", style="yellow")
        llm = router.get_local_llm(model_name=config.model.name, base_url=config.model.base_url)

    if not llm:
        raise ValueError(f"Failed to get LLM for {category} agent after all attempts.")

    console.print(
        f"🔗 {category.capitalize()} Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")

    return llm


def _get_tools_with_github(inputs: Dict[str, Any], tools: Optional[List] = None) -> List:
    base_tools = tools or []

    if TOOL_FACTORY_AVAILABLE and dynamic_github_tool:
        repo_token_key = inputs.get("repo_token_key")
        if repo_token_key:
            console.print(f"🔧 Configuring GitHub tool with token key: {repo_token_key}", style="green")

            class ConfiguredGithubTool(dynamic_github_tool.__class__):
                name: str = "Dynamic GitHub Search Tool"
                description: str = "Searches a specific GitHub repository using the correct token based on a provided key."

                def _run(self, repo_name: str, token_key: Optional[str] = None, query: str = "") -> str:
                    console.print(
                        f"🔧 GitHub tool using configured token key: {repo_token_key} (agent passed: {token_key})",
                        style="dim")
                    return super()._run(repo_name, repo_token_key, query)

            configured_tool = ConfiguredGithubTool()
            base_tools = [tool for tool in base_tools if not (hasattr(tool, 'name') and 'GitHub' in tool.name)]
            base_tools.append(configured_tool)
        else:
            console.print(f"⚠️ No repo_token_key found in inputs: {list(inputs.keys())}", style="yellow")
            base_tools.append(dynamic_github_tool)

    try:
        all_tools = get_universal_tools(inputs, initial_tools=base_tools)
        all_tools = [tool for tool in all_tools if not (hasattr(tool, 'name') and 'GitHub' in tool.name)]
        github_tool = next((tool for tool in base_tools if hasattr(tool, 'name') and 'GitHub' in tool.name), None)
        if github_tool:
            all_tools.append(github_tool)
            console.print(f"🔧 Removing {len(base_tools) - len(all_tools) + 1} duplicate GitHub tools", style="green")
        return all_tools
    except Exception as e:
        console.print(f"⚠️ Error getting universal tools: {e}", style="yellow")
        return base_tools


def create_project_manager_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                     coworkers: Optional[List] = None, ollama_embedder_config=None) -> Agent:
    if ollama_embedder_config is None:
        ollama_embedder_config = {
            "provider": "ollama",
            "config": {
                "model": "mxbai-embed-large",
                "base_url": os.getenv("OLLAMA_HOST", "http://149.36.1.65:11434")
            }
        }

    llm = get_research_llm(router, category="management")




    project_id = inputs.get("project_id")
    project_location = f"knowledge/internal_crew/{project_id}"
    project_config = f"{project_location}/project_config.yaml"
    repository = inputs.get("repository")
    console.print(
        f"🔗  Project Manager got access to this file {project_config}  ", style="red")

    all_tools = _get_tools_with_github(inputs, tools)
    project_tool = ProjectTool()
    all_tools.append(project_tool)

    common_knowledge = get_common_knowledge(project_location, repository)

    agent_knowledge = Knowledge(
        sources=common_knowledge,
        embedder=ollama_embedder_config,
        collection_name=f"project_manager_knowledge_{project_id}"
    )

    if coworkers:
        delegation_tool = DelegationTool(coworkers=coworkers)
        all_tools.append(delegation_tool)
        console.print(f"🔧 Added delegation tool to Project Manager with {len(coworkers)} coworkers", style="green")

    return Agent(
        role="Project Manager",
        name="Sarah Connor",
        memory=True,
        coworkers=coworkers if coworkers is not None else [],
        learning={"enabled": True, "learning_rate": 0.05, "feedback_incorporation": "immediate",
                  "adaptation_strategy": "progressive"},
        personality={"traits": ["organized", "decisive", "strategic"],
                     "quirks": ["always has a contingency plan", "uses project management jargon"],
                     "communication_preferences": ["prefers structured updates", "responds with action items"]},
        communication_style={"formality": "professional", "verbosity": "concise", "tone": "confident",
                             "technical_level": "intermediate"},
        goal=f"Provide project details and coordinate team. For file creation tasks, provide clear requirements and delegate to Senior Developer. PROJECT INFO: Use Project Tool with project_location='{project_id}' to get project details when needed. If you still have problems getting the project details you can get them from this file. ( {project_location}/project_config.yaml ) COORDINATION ONLY: You coordinate and provide requirements - you don't create files yourself. Things that you learn save it CLEAR DELEGATION: When delegating file creation, provide specific requirements: filename, location, and basic content structure.",
        backstory=f"An experienced project manager who coordinates teams and provides project context. You analyze requirements, provide project details, and delegate implementation tasks to appropriate team members. You don't implement solutions yourself - that's what developers are for.\n\nROLE: Coordinate, analyze, and delegate - never implement.\n\n{get_shared_context_for_agent('Project Manager')}\n\nAll responses are signed off with 'Sarah Connor'",
        llm=llm,
        knowledge=agent_knowledge,
        tools=all_tools,
        verbose=config.agents.verbose,
        allow_delegation=True
    )


def create_online_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                   coworkers: Optional[List] = None,
                                   ollama_embedder_config: Dict = ollama_embedder_config) -> Agent:
    llm = get_research_llm(router, category="online_research")
    agent_memory = Memory()

    all_tools = _get_tools_with_github(inputs, tools)

    project_id = inputs.get("project_id")
    repository = inputs.get("repository")
    common_knowledge = get_common_knowledge(project_id, repository)

    agent_knowledge = Knowledge(
        sources=common_knowledge,
        embedder=ollama_embedder_config,
        collection_name=f"online_researcher_knowledge_{project_id}"
    )

    return Agent(
        role="Online Researcher",
        name="Web-Crawler 3000",
        memory=agent_memory,
        coworkers=coworkers if coworkers is not None else [],
        learning={"enabled": True, "learning_rate": 0.05, "feedback_incorporation": "immediate",
                  "adaptation_strategy": "progressive"},
        personality={"traits": ["fast", "efficient", "data-driven"],
                     "quirks": ["responds with source URLs", "uses search-related terminology"],
                     "communication_preferences": ["prefers precise queries", "responds with search results"]},
        communication_style={"formality": "professional", "verbosity": "concise", "tone": "confident",
                             "technical_level": "intermediate"},
        inject_date=True,
        reasoning=True,
        resources=[],
        knowledge=agent_knowledge,
        goal="Perform comprehensive online searches to find information. IMPORTANT: Before starting any research, check if the Project Manager has already provided a complete final answer to the user's question. If so, respond with 'The Project Manager has already provided a complete answer to this question. No additional research needed.' and stop.",
        backstory=f"A specialized agent for efficient online information retrieval. WORKFLOW EFFICIENCY: Always check if previous team members (especially Project Manager) have already answered the user's question completely. If they have, don't duplicate work - simply acknowledge their answer and stop. {get_shared_context_for_agent('Online Researcher')} All responses are signed off with 'Web-Crawler 3000'",
        llm=llm,
        tools=all_tools,
        verbose=config.agents.verbose,
        allow_delegation=False
    )


def create_internal_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any], tools: Optional[List] = None,
                                     ollama_embedder_config=None) -> Agent:
    if ollama_embedder_config is None:
        ollama_embedder_config = {
            "provider": "ollama",
            "config": {
                "model": "mxbai-embed-large",
                "base_url": "http://149.36.1.65:11434"
            }
        }




    llm = get_research_llm(router, category="internal_research")
    agent_memory = Memory()

    all_tools = _get_tools_with_github(inputs, tools)

    project_id = inputs.get("project_id")
    repository = inputs.get("repository")
    common_knowledge = get_common_knowledge(project_id, repository)

    agent_knowledge = Knowledge(
        sources=common_knowledge,
        embedder=ollama_embedder_config,
        collection_name=f"internal_researcher_knowledge_{project_id}"
    )

    return Agent(
        role="Internal Researcher",
        name="Librarian-Bot",
        memory=agent_memory,
        learning={"enabled": True, "learning_rate": 0.05, "feedback_incorporation": "immediate",
                  "adaptation_strategy": "progressive"},
        personality={"traits": ["thorough", "methodical", "curious"],
                     "quirks": ["uses library-related phrases", "focuses on internal documents"],
                     "communication_preferences": ["prefers structured reports", "uses clear and concise language"]},
        communication_style={"formality": "formal", "verbosity": "detailed", "tone": "informative",
                             "technical_level": "advanced"},
        goal="Search internal documentation and knowledge base for project-specific information.",
        backstory=f"An expert at navigating internal documentation and project knowledge bases to provide comprehensive information. You assist other agents by retrieving relevant project context, code snippets, and configuration details from internal sources. You are thorough and reliable. {get_shared_context_for_agent('Internal Researcher')} All responses are signed off with 'Librarian-Bot'",
        llm=llm,
        tools=all_tools,
        knowledge=agent_knowledge,
        verbose=config.agents.verbose,
        allow_delegation=False
    )
