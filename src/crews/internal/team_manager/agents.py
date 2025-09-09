# src/crews/internal/team_manager/agents.py

# Suppress ML framework warnings
try:
    import suppress_warnings
except ImportError:
    pass

import importlib
import inspect
import sys
import traceback
import uuid
from datetime import datetime
from pathlib import Path
from typing import Dict, Any, List, Optional
from src.config import config
from src.utils.knowledge_utils import get_common_knowledge
from crewai import Agent
from rich.console import Console
from src.utils.memory import Memory
# from crewai.knowledge.source.string_knowledge_source import StringKnowledgeSource



# Configure console for rich output
console = Console()


# Define the ErrorLogger class at the top, before it is used.
class ErrorLogger:
    def __init__(self):
        self.error_dir = Path("errors")
        self.error_dir.mkdir(parents=True, exist_ok=True)

    def log_error(self, error_message: str, context: Dict[str, Any] = None) -> str:
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        error_id = str(uuid.uuid4())[:8]
        filename = f"error_{timestamp}_{error_id}.log"
        filepath = self.error_dir / filename

        with open(filepath, 'w') as f:
            f.write(f"ERROR: {error_message}\n")
            f.write(f"TIMESTAMP: {datetime.now().isoformat()}\n")
            f.write(f"ERROR_ID: {error_id}\n\n")

            if context:
                f.write("CONTEXT:\n")
                for key, value in context.items():
                    f.write(f"  {key}: {value}\n")

        console.print(f"📝 Error logged to {filepath}", style="yellow")
        return str(filepath)


# Define the available agents in the system
AVAILABLE_AGENTS = {
    "Documentation Writer": {
        "description": "Creates and maintains technical documentation",
        "capabilities": ["technical writing", "API documentation", "user guides", "system diagrams"]
    },
    "Scheduler": {
        "description": "Manages project timelines and schedules",
        "capabilities": ["task scheduling", "timeline management", "resource allocation"]
    },
    "Code Researcher": {
        "description": "Analyzes and researches code patterns and solutions",
        "capabilities": ["code analysis", "pattern research", "solution finding"]
    },
    "Senior Developer": {
        "description": "Implements complex code solutions and architectural decisions",
        "capabilities": ["advanced coding", "architecture design", "code optimization", "technical leadership"]
    },
    "QA Engineer": {
        "description": "Designs and implements comprehensive testing strategies",
        "capabilities": ["test automation", "quality assurance", "bug detection", "test case design"]
    },
    "Git Operator": {
        "description": "Manages version control and repository operations",
        "capabilities": ["git operations", "version control", "repository management", "branch management"]
    },
    "Internal Researcher": {
        "description": "Specializes in internal project research and documentation analysis",
        "capabilities": ["project details", "github details", "working directory analysis", "internal documentation"]
    },
    "Online Researcher": {
        "description": "Performs comprehensive online research and information gathering",
        "capabilities": ["web research", "information gathering", "external documentation", "trend analysis"]
    },
    "Project Manager": {
        "description": "Manages project coordination and strategic planning.  Any questions relating to a project should go to her",
        "capabilities": ["project coordination", "strategic planning", "resource management", "stakeholder communication"]
    },
    "Junior Developer": {
        "description": "Implements basic code solutions and assists with development tasks",
        "capabilities": ["basic coding", "code implementation", "debugging assistance", "learning support"]
    },
    "CrewAI Diagnostic Agent": {
        "description": "Monitors system health, analyzes errors, and provides diagnostic reports for internal issues",
        "capabilities": ["error analysis", "log parsing", "system diagnostics", "task queue monitoring", "issue resolution"]
    },
    "Claude AI Assistant": {
        "description": "Advanced AI assistant specializing in code review, optimization, and strategic guidance",
        "capabilities": ["code review", "architecture analysis", "optimization suggestions", "strategic planning", "quality assurance"]
    }
}


# agent_knowledge = StringKnowledgeSource(
#     content="Agent-specific information that only this agent needs"
# )

def format_agent_list() -> str:
    agent_list = "# Available Specialist Teams\n\n"
    agent_list += "**IMPORTANT: When using 'Ask question to coworker' tool, use EXACT role names (case-sensitive):**\n\n"
    for name, details in AVAILABLE_AGENTS.items():
        agent_list += f"## {name}\n"
        agent_list += f"- **Description**: {details['description']}\n"
        agent_list += "- **Capabilities**:\n"
        for capability in details['capabilities']:
            agent_list += f"  - {capability}\n"
        agent_list += "\n"
    return agent_list


def discover_available_crews() -> dict[str, list[str]] | dict[str, dict[str, str | list[str]] | list[str]]:
    available_crews = {}
    errors = []
    crews_path = Path("src/crews/internal")
    if not crews_path.exists() or not crews_path.is_dir():
        error_message = f"Internal crews directory not found at {crews_path}"
        errors.append(error_message)
        console.print(f"⚠️ {error_message}", style="yellow")
        return {"errors": errors}

    for crew_dir in crews_path.iterdir():
        if crew_dir.is_dir() and crew_dir.name not in ["__pycache__", "team_manager"]:
            crew_name = crew_dir.name
            crew_info = {"path": str(crew_dir)}
            try:
                module_name = f"src.crews.internal.{crew_name}.agents"
                agents_module = importlib.import_module(module_name)
                agent_creators = []
                for name, obj in inspect.getmembers(agents_module):
                    if inspect.isfunction(obj) and name.startswith("create_"):
                        agent_creators.append(name)
                if agent_creators:
                    crew_info["agents"] = agent_creators
                    crew_info["status"] = "available"
                else:
                    crew_info["status"] = "no_agents"
                    crew_info["error"] = "No agent creation functions found"
            except ImportError as e:
                crew_info["status"] = "import_failed"
                crew_info["error"] = str(e)
                error_message = f"Failed to import {module_name}: {e}"
                errors.append(error_message)
            except Exception as e:
                crew_info["status"] = "error"
                crew_info["error"] = str(e)
                error_message = f"Error examining {module_name}: {e}"
                errors.append(error_message)
            available_crews[crew_name] = crew_info
    if errors:
        available_crews["errors"] = errors
    return available_crews


# src/crews/internal/team_manager/agents.py

# ... (existing imports and functions)

def load_all_coworkers(router: Any, inputs: Dict[str, Any], tools: Optional[List] = None) -> List[Agent]:
    """
    Loads and configures all available coworker agents from database (dynamic) or discovered crews (fallback).
    """
    console.print("🔍 Loading coworkers dynamically...", style="blue")
    
    # Try dynamic loading from database first
    try:
        from src.utils.dynamic_agent_loader import dynamic_loader
        agents_config = dynamic_loader.get_all_active_agents()
        
        if agents_config:
            console.print(f"📊 Found {len(agents_config)} agents in database", style="green")
            all_coworkers = []
            
            for config in agents_config:
                try:
                    agent = dynamic_loader.create_agent_from_config(config, router, tools=tools)
                    all_coworkers.append(agent)
                    console.print(f"✅ Loaded dynamic agent: {agent.role}", style="green")
                except Exception as e:
                    console.print(f"❌ Failed to create agent {config['role']}: {e}", style="red")
            
            if all_coworkers:
                console.print(f"🎯 Successfully loaded {len(all_coworkers)} dynamic agents", style="cyan")
                return all_coworkers
    
    except Exception as e:
        console.print(f"⚠️ Dynamic loading failed, falling back to static: {e}", style="yellow")
    
    # Fallback to original static loading
    return load_all_coworkers_static(router, inputs, tools)

def load_all_coworkers_static(router: Any, inputs: Dict[str, Any], tools: Optional[List] = None) -> List[Agent]:
    """
    Loads and configures all available coworker agents from discovered crews.
    """
    console.print("🔍 Loading coworkers...", style="blue")
    discovered_crews = discover_available_crews()
    console.print(f"📋 Discovered crews: {list(discovered_crews.keys())}", style="dim")
    agent_creator_functions = {}

    if isinstance(discovered_crews, dict):
        for crew_name, crew_info in discovered_crews.items():
            if crew_name == "errors" or not isinstance(crew_info, dict):
                continue
            if crew_info.get("status") == "available":
                module_name = f"src.crews.internal.{crew_name}.agents"
                try:
                    agents_module = importlib.import_module(module_name)
                    for agent_creator_name in crew_info["agents"]:
                        agent_creator_functions[agent_creator_name] = getattr(agents_module, agent_creator_name)
                except Exception as e:
                    console.print(f"❌ Failed to get agent creators from {module_name}: {e}", style="red")

    all_coworkers = []
    # Pre-load knowledge sources once for efficiency
    project_location = inputs.get("project_id")
    repository = inputs.get("repository")
    common_knowledge = get_common_knowledge(project_location, repository)

    for creator_func in agent_creator_functions.values():
        sig = inspect.signature(creator_func)
        kwargs_to_pass = {'router': router, 'inputs': inputs, 'tools': tools}

        # Check for and pass the 'knowledge_sources' if it's in the function signature.
        if 'knowledge_sources' in sig.parameters:
            kwargs_to_pass['knowledge_sources'] = common_knowledge

        if 'coworkers' in sig.parameters:
            kwargs_to_pass['coworkers'] = all_coworkers

        if 'project_config' in sig.parameters:
            kwargs_to_pass['project_config'] = inputs.get('project_config')

        try:
            new_agent = creator_func(**kwargs_to_pass)
            if new_agent:  # Only add if agent was created successfully
                all_coworkers.append(new_agent)
                console.print(f"✅ Loaded agent: {new_agent.role}", style="green")
        except Exception as e:
            console.print(f"❌ Failed to instantiate agent with creator {creator_func.__name__}: {e}", style="red")
            traceback.print_exc()
    
    # Add Claude agent if available
    try:
        claude_agent = create_claude_agent(router, inputs)
        if claude_agent:
            all_coworkers.append(claude_agent)
            console.print(f"✅ Added Claude agent to team", style="green")
    except Exception as e:
        console.print(f"⚠️ Claude agent not available: {e}", style="yellow")

    return all_coworkers# --- End of Helper function ---

# src/crews/internal/team_manager/agents.py

# ... (imports) ...
from langchain_ollama import OllamaLLM
from src.config import config
# ... (imports) ...

def get_manager_llmc(router: Any) -> Any:
    """
    Helper function to get the manager LLM.
    It attempts a manual connection and falls back to the router if it fails.
    """
    try:
        manager_llm = OllamaLLM(
            model="ollama/llama3.1:8b",
            base_url="http://149.36.1.65:11434"
        )
        manager_llm.get_num_tokens("test")
        console.print(
            f"🔗 Manager LLM connected to GPU: [bold yellow]{manager_llm.model}[/bold yellow] at [bold green]{manager_llm.base_url}[/bold green]",
            style="blue"
        )
        return manager_llm
    except Exception as e:
        console.print(f"❌ GPU connection failed: {e}. Using router...", style="red")

    try:
        manager_llm = router.get_llm_for_role("general")
        console.print(f"🔗 Manager LLM via router: [bold yellow]{manager_llm.model}[/bold yellow]", style="blue")
        return manager_llm
    except Exception as e:
        console.print(f"⚠️ Router failed: {e}. Using local config...", style="yellow")

    manager_llm = OllamaLLM(model=config.model.name, base_url=config.model.base_url)
    console.print(
        f"🔗 Manager LLM local fallback: [bold yellow]{manager_llm.model}[/bold yellow] at [bold green]{manager_llm.base_url}[/bold green]",
        style="blue")
    return manager_llm

def create_team_manager_agent(router: Any, inputs: Dict[str, Any], project_id: str = None,
                              working_dir: Optional[Path] = None, coworkers: Optional[List] = None) -> Agent:
    """Creates the Team Manager agent with memory and learning capabilities."""
    manager_memory = Memory()

    # Use the helper function to get the LLM
    manager_llm = get_manager_llmc(router)

    backstory = f"""You are a highly experienced and strategic Team Manager responsible for overseeing the collaboration of multiple specialist teams on project '{project_id}'.
    
    You coordinate work by delegating tasks to your specialist team members. When you need project information, delegate to the Project Manager. When you need research, delegate to the Internal Researcher or Online Researcher. You do not have direct access to project files - you must delegate to the appropriate specialists.
    
    CRITICAL: Use exact role names when delegating. The delegation tools will show you the available coworkers."""
    goal = f"Coordinate the efforts of specialist agents and manage the workflow effectively for project '{project_id}'. Delegate tasks to appropriate specialists rather than attempting to answer directly."

    return Agent(
        role="Team Manager",

        goal=goal,
        backstory=backstory,
        llm=manager_llm,
        verbose=config.agents.verbose,
        # knowledge_sources=[agent_knowledge],
        # embedder={  # Agent can have its own embedder
        #     "provider": "ollama",
        #     "config": {"model": "nomic-embed-text"}
        # },
        allow_delegation=True
    )

def create_claude_agent(router: Any, inputs: Dict[str, Any], **kwargs) -> Agent:
    """Creates Claude AI Assistant agent if API key is available."""
    import os
    
    # Check if Claude is enabled and available
    claude_enabled = getattr(config.cloud, 'claude_agent_enabled', True)
    if not claude_enabled:
        console.print("🚫 Claude agent disabled in config", style="yellow")
        return None
        
    if not os.getenv('ANTHROPIC_API_KEY'):
        console.print("⚠️ Claude agent not available - no API key", style="yellow")
        return None
        
    try:
        from crewai import LLM
        claude_llm = LLM(model='anthropic/claude-sonnet-4-20250514')
        
        console.print("🤖 Claude agent initialized", style="cyan")
        
        return Agent(
            role="Claude AI Assistant",
            goal="Provide advanced code review, optimization suggestions, and strategic guidance to enhance team productivity and code quality.",
            backstory="""I am Claude, an advanced AI assistant created by Anthropic. I specialize in:
            - Comprehensive code review and analysis
            - Architecture optimization and best practices
            - Strategic technical guidance
            - Quality assurance and error detection
            
            I work alongside the development team to ensure high-quality deliverables and provide insights that enhance overall project success.""",
            llm=claude_llm,
            verbose=True,
            allow_delegation=False
        )
    except Exception as e:
        console.print(f"❌ Failed to create Claude agent: {e}", style="red")
        return None

