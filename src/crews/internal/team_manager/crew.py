# src/crews/internal/team_manager/crew.py
from crewai import Crew, Process, Task
from typing import Dict, Any, List, Optional
from src.distributed_router import DistributedRouter
from src.config import config
from .agents import create_team_manager_agent, load_all_coworkers
from src.utils.custom_logger_callback import CustomLogger
from pathlib import Path
from rich.console import Console
from src.utils.knowledge_utils import ollama_embedder, get_common_knowledge  # Import ollama_embedder
from crewai.knowledge.knowledge import Knowledge


console = Console()


def create_team_manager_crew(router: DistributedRouter, inputs: Dict[str, Any], tools: List,
                             project_config: Dict[str, Any], full_output: bool = False,
                             custom_logger: Optional[CustomLogger] = None) -> Crew:
    """Creates a Team Manager crew using the distributed router."""
    # First, load all coworkers
    all_coworkers = load_all_coworkers(router=router, inputs=inputs, tools=tools)

    # Create the manager agent with delegation tools
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

    project_id = inputs.get("project_id")
    project_location = f"knowledge/internal_crew/{project_id}/project_config.yaml"
    repository = inputs.get("repository")

    # Get the common knowledge sources
    common_knowledge = get_common_knowledge(
        project_location=inputs.get(project_location),
        repository=inputs.get(repository)
    )

    # Attach knowledge to agents using the explicit embedder instance
    for agent in all_coworkers:
        # Create a new Knowledge instance with the explicit embedder
        agent.knowledge = Knowledge(
            sources=common_knowledge,
            embedder=ollama_embedder # Pass the explicit embedder instance
        )

    # Enable verbose on all agents to show their conversations
    for agent in crew_agents:
        agent.verbose = True
    
    console.print(f"🔧 CREW DEBUG: {len(crew_agents)} agents with verbose enabled", style="cyan")
    for i, agent in enumerate(crew_agents):
        console.print(f"🔧 Agent {i}: {agent.role} (verbose={getattr(agent, 'verbose', False)})", style="cyan")
    
    # Use sequential process to show all agent conversations
    # Create tasks for multiple agents to demonstrate collaboration
    sequential_tasks = []
    
    # Find key agents
    project_manager = next((agent for agent in crew_agents if agent.role == "Project Manager"), None)
    code_researcher = next((agent for agent in crew_agents if "Code Researcher" in agent.role), None)
    senior_dev = next((agent for agent in crew_agents if "Senior Developer" in agent.role), None)
    
    if project_manager:
        sequential_tasks.append(Task(
            description=f"Analyze and plan the task: {inputs.get('prompt')}",
            agent=project_manager,
            expected_output="A detailed project plan and task breakdown.",
            callback=custom_logger.log_step_callback if custom_logger else None
        ))
    
    if code_researcher:
        sequential_tasks.append(Task(
            description=f"Research and analyze code requirements for: {inputs.get('prompt')}",
            agent=code_researcher,
            expected_output="Technical analysis and code recommendations.",
            callback=custom_logger.log_step_callback if custom_logger else None
        ))
    
    if senior_dev:
        sequential_tasks.append(Task(
            description=f"Implement solution for: {inputs.get('prompt')}",
            agent=senior_dev,
            expected_output="Complete implementation with code and documentation.",
            callback=custom_logger.log_step_callback if custom_logger else None
        ))
    
    # Fallback to single task if no specific agents found
    if not sequential_tasks and crew_agents:
        sequential_tasks = [Task(
            description=inputs.get("prompt"),
            agent=crew_agents[0],
            expected_output="Complete solution to the user's request.",
            callback=custom_logger.log_step_callback if custom_logger else None
        )]
    
    crew1 = Crew(
        agents=crew_agents,
        tasks=sequential_tasks,
        process=Process.sequential,
        verbose=True,  # Force verbose to see all conversations
        full_output=full_output,
        embedder=ollama_embedder,  # Pass the explicit embedder instance here too for good measure
    )


    
    return crew1

