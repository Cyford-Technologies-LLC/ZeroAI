# src/agents/base_agents.py

from crewai import Agent
from typing import Dict, Any, List, Optional
from distributed_router import DistributedRouter
from src.config import config
from rich.console import Console

console = Console()

def create_researcher(router: DistributedRouter, inputs: Dict[str, Any], category: str = "research") -> Agent:
    """Create a Researcher agent with feedback loop integration."""
    task_description = "Conduct thorough research on code, projects, and technical solutions."
    
    # Model preference order: llama3.1:8b -> llama3.2:latest -> gemma2:2b -> llama3.2:1b
    preferred_models = ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llama3.2:1b"]
    
    # Try to get learning-based model preference
    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference(category)
        if category_model:
            if category_model not in preferred_models:
                preferred_models.insert(0, category_model)
    except ImportError:
        pass  # Learning module not available
    
    llm = None
    try:
        # Use the updated get_llm_for_task with preferred models
        llm = router.get_llm_for_task(task_description, preferred_models)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for researcher agent via router: {e}", style="yellow")
        # Fall back to local model
        llm = router.get_local_llm("llama3.2:1b")
    
    if not llm:
        raise ValueError("Failed to get LLM for researcher agent after all attempts.")
    
    console.print(
        f"🔗 Researcher Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")
    
    return Agent(
        role="Research Specialist",
        goal="Analyze codebases, technical documents, and project requirements to provide thorough insights.",
        backstory="An expert researcher with deep technical knowledge, specializing in understanding complex systems and extracting key insights.",
        llm=llm,
        verbose=config.agents.verbose,
        allow_delegation=False
    )

def create_analyst(router: DistributedRouter, inputs: Dict[str, Any], category: str = "research") -> Agent:
    """Create an Analyst agent with feedback loop integration."""
    task_description = "Analyze research findings and provide actionable insights."
    
    # Model preference order: llama3.1:8b -> llama3.2:latest -> gemma2:2b -> llama3.2:1b
    preferred_models = ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llama3.2:1b"]
    
    # Try to get learning-based model preference
    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference(category)
        if category_model:
            if category_model not in preferred_models:
                preferred_models.insert(0, category_model)
    except ImportError:
        pass  # Learning module not available
    
    llm = None
    try:
        # Use the updated get_llm_for_task with preferred models
        llm = router.get_llm_for_task(task_description, preferred_models)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for analyst agent via router: {e}", style="yellow")
        # Fall back to local model
        llm = router.get_local_llm("llama3.2:1b")
    
    if not llm:
        raise ValueError("Failed to get LLM for analyst agent after all attempts.")
    
    console.print(
        f"🔗 Analyst Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")
    
    return Agent(
        role="Research Analyst",
        goal="Synthesize research findings into actionable insights and recommendations.",
        backstory="A skilled analyst with expertise in interpreting complex data and research findings.",
        llm=llm,
        verbose=config.agents.verbose,
        allow_delegation=False
    )

# Add more base agent creators as needed...

def create_documentation_specialist(router: DistributedRouter, inputs: Dict[str, Any], category: str = "documentation") -> Agent:
    """Create a Documentation Specialist agent with feedback loop integration."""
    task_description = "Create and update technical documentation for code and projects."
    
    # Model preference order: llama3.2:latest -> llama3.1:8b -> gemma2:2b -> llama3.2:1b
    preferred_models = ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"]
    
    # Try to get learning-based model preference
    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference(category)
        if category_model:
            if category_model not in preferred_models:
                preferred_models.insert(0, category_model)
    except ImportError:
        pass  # Learning module not available
    
    llm = None
    try:
        # Use the updated get_llm_for_task with preferred models
        llm = router.get_llm_for_task(task_description, preferred_models)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for documentation agent via router: {e}", style="yellow")
        # Fall back to local model
        llm = router.get_local_llm("llama3.2:1b")
    
    if not llm:
        raise ValueError("Failed to get LLM for documentation agent after all attempts.")
    
    console.print(
        f"🔗 Documentation Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")
    
    return Agent(
        role="Technical Documentation Specialist",
        goal="Create clear, comprehensive, and accurate technical documentation.",
        backstory="An expert technical writer with experience documenting complex software systems and APIs.",
        llm=llm,
        verbose=config.agents.verbose,
        allow_delegation=False
    )

def create_repo_manager(router: DistributedRouter, inputs: Dict[str, Any], category: str = "repo_management") -> Agent:
    """Create a Repository Manager agent with feedback loop integration."""
    task_description = "Manage Git repositories, branches, and code organization."
    
    # Model preference order: llama3.2:latest -> llama3.1:8b -> gemma2:2b -> llama3.2:1b
    preferred_models = ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"]
    
    # Try to get learning-based model preference
    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference(category)
        if category_model:
            if category_model not in preferred_models:
                preferred_models.insert(0, category_model)
    except ImportError:
        pass  # Learning module not available
    
    llm = None
    try:
        # Use the updated get_llm_for_task with preferred models
        llm = router.get_llm_for_task(task_description, preferred_models)
    except Exception as e:
        console.print(f"⚠️ Failed to get optimal LLM for repo manager agent via router: {e}", style="yellow")
        # Fall back to local model
        llm = router.get_local_llm("llama3.2:1b")
    
    if not llm:
        raise ValueError("Failed to get LLM for repo manager agent after all attempts.")
    
    console.print(
        f"🔗 Repo Manager Agent connecting to model: [bold yellow]{llm.model}[/bold yellow] at [bold green]{llm.base_url}[/bold green]",
        style="blue")
    
    return Agent(
        role="Repository Manager",
        goal="Efficiently manage code repositories, versioning, and Git operations.",
        backstory="An experienced DevOps engineer specializing in version control systems and repository management.",
        llm=llm,
        verbose=config.agents.verbose,
        allow_delegation=False
    )