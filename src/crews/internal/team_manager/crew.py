# src/crews/internal/team_manager/crew.py
from crewai import Crew, Process, Task
from typing import Dict, Any, List, Optional
from distributed_router import DistributedRouter
from src.config import config
from .agents import create_team_manager_agent, load_all_coworkers
from src.utils.custom_logger_callback import CustomLogger
from pathlib import Path
from rich.console import Console

console = Console()


def create_team_manager_crew(router: DistributedRouter, inputs: Dict[str, Any], tools: List,
                             project_config: Dict[str, Any], full_output: bool = False,
                             custom_logger: Optional[CustomLogger] = None) -> Crew:
    """Creates a Team Manager crew using the distributed router."""
    # First, load all coworkers
    all_coworkers = load_all_coworkers(router=router, inputs=inputs, tools=tools)

    # Create the manager agent (no tools allowed in hierarchical process)
    manager_agent = create_team_manager_agent(
        router=router,
        project_id=inputs.get("project_id"),
        working_dir=inputs.get("working_dir", Path("/tmp")),
        inputs=inputs,
        coworkers=all_coworkers
    )

    # Define tasks directly within this function
    manager_tasks = [
        Task(
            description=inputs.get("prompt"),
            agent=manager_agent,
            expected_output="A final, complete, and thoroughly reviewed solution to the user's request. "
                            "This may include code, documentation, or other relevant artifacts.",
            # Pass the callback directly
            callback=custom_logger.log_step_callback if custom_logger else None
        )
    ]

    # Create the list of agents for the crew (manager is handled separately)
    crew_agents = all_coworkers
    
    # Debug: Print detailed delegation information
    console.print(f"🔧 DELEGATION DEBUG:", style="cyan")
    console.print(f"🔧 Manager agent role: '{manager_agent.role}'", style="cyan")
    console.print(f"🔧 Manager agent type: {type(manager_agent)}", style="cyan")
    console.print(f"🔧 Total crew agents: {len(crew_agents)}", style="cyan")
    for i, agent in enumerate(crew_agents):
        console.print(f"🔧 Agent {i}: role='{agent.role}', type={type(agent)}, id={id(agent)}", style="cyan")
    
    # Check if manager has coworkers set
    if hasattr(manager_agent, 'coworkers'):
        console.print(f"🔧 Manager coworkers: {[c.role for c in manager_agent.coworkers] if manager_agent.coworkers else 'None'}", style="cyan")
    else:
        console.print(f"🔧 Manager has no coworkers attribute", style="cyan")

    # Debug: Check manager tools after crew creation
    console.print(f"🔧 Manager tools before crew creation: {getattr(manager_agent, 'tools', 'No tools attr')}", style="cyan")
    
    # Create and return the crew with the correct agent list
    crew = Crew(
        agents=crew_agents,
        tasks=manager_tasks,
        manager_agent=manager_agent,
        process=Process.hierarchical,
        verbose=config.agents.verbose,
        full_output=full_output,
    )
    
    # Debug: Check manager tools after crew creation
    console.print(f"🔧 Manager tools after crew creation: {getattr(manager_agent, 'tools', 'No tools attr')}", style="cyan")
    if hasattr(manager_agent, 'tools') and manager_agent.tools:
        for tool in manager_agent.tools:
            console.print(f"🔧 Tool: {tool.name if hasattr(tool, 'name') else str(tool)}", style="cyan")
    
    return crew

