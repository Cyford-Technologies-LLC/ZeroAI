# src/crews/internal/team_manager/crew.py
from crewai import LLM, Crew, Process, Task
from typing import Dict, Any, List, Optional
from src.distributed_router import DistributedRouter
from src.config import config
from .agents import create_team_manager_agent, load_all_coworkers
from src.utils.custom_logger_callback import CustomLogger
from pathlib import Path
from rich.console import Console
from src.utils.knowledge_utils import get_common_knowledge # Removed get_ollama_client as it's not used directly here
from crewai.knowledge.knowledge import Knowledge
from crewai.knowledge.source.crew_docling_source import CrewDoclingSource
from src.utils.shared_knowledge import get_shared_context_for_agent, get_agent_learning_path , save_agent_learning  ,get_agent_learning_path , load_team_briefing




from langchain_ollama import OllamaEmbeddings # Import OllamaEmbeddings for the Knowledge object
#from langchain_community.embeddings import OllamaEmbeddings
console = Console()


def create_team_manager_crew(router: DistributedRouter, inputs: Dict[str, Any], tools: List,
                             project_config: Dict[str, Any], full_output: bool = False,
                             custom_logger: Optional[CustomLogger] = None) -> Crew:
    """Creates a Team Manager crew using the distributed router."""

    # First, load all coworkers
    all_coworkers = load_all_coworkers(router=router, inputs=inputs, tools=tools)

    # Move assignment to the top so it's defined
    crew_agents = all_coworkers

    # Create the manager agent with delegation tools
    manager_agent = create_team_manager_agent(
        router=router,
        project_id=inputs.get("project_id"),
        working_dir=inputs.get("working_dir", Path("/tmp")),
        inputs=inputs,
        coworkers=all_coworkers
    )

    # ... (tasks definition) ...
    sequential_tasks = []

    # Enable verbose on all agents
    for agent in crew_agents:
        agent.verbose = True

    # Use team manager as prompt refiner for now
    prompt_refiner = manager_agent



    project_id = inputs.get("project_id")
    repository = inputs.get("repository")
    working_dir = inputs.get('working_directory', inputs.get('working_dir', 'unknown'))
    project_location = f"/app/knowledge/internal_crew/{project_id}"
    project_config = f"{project_location}/project_config.yaml"
    learning_doc = "knowledge/internal_crew/agent_learning/learning_tool.md"

    if not project_id:
        raise ValueError("The 'project_id' key is missing from the inputs.")


    All_DETAILS = f"""  Paths are not absolute.  
                    Project location: {project_location}
                    Working directory:  {working_dir}
                    Project ID:         {project_id}
                    Project location:   {project_location}
                    Project Config:     {project_config} 
                
                    
                    TRAINING / LEARNING AND TOOLS:
                    Team Briefing:      /app/knowledge/internal_crew/agent_learning/team_briefing.md
                    Docker User Guide:  /app/knowledge/internal_crew/agent_learning/docker_usage_guide.md
                    Tool Usage:         /app/knowledge/internal_crew/agent_learning/tool_usage_guide.md
                    learning_Tool:      {learning_doc}
                    
                    After successfully setting up the project with Docker Compose, performing a code review, and checking for errors, 
                    use the `Learning Tool` to save the successful configuration steps and findings. 
                    Use the filename `docker_setup_details.md`.
                    save your learned knowledge
                    """
    refine_prompt_task = Task(
        description=f"""
         Analyze and correct the user's initial prompt for grammar, spelling, and clarity.
         The prompt is: '{inputs.get('prompt')}'

         Rewrite the prompt to be highly specific and actionable for a senior developer AI agent.
         Return ONLY the rewritten, refined prompt as your final answer.
         """,
        agent=prompt_refiner,
        expected_output="A perfectly formatted, grammatically correct, and highly specific prompt for a development task.",
        callback=custom_logger.log_step_callback if custom_logger else None
    )
    sequential_tasks.append(refine_prompt_task)

   # Find key agents and create tasks (your existing logic)
    project_manager = next((agent for agent in crew_agents if agent.role == "Project Manager"), None)

    docker_specialist = next((agent for agent in crew_agents if agent.role == "Docker Specialist"), None)
    code_researcher = next((agent for agent in crew_agents if "Code Researcher" in agent.role), None)
    senior_dev = next((agent for agent in crew_agents if "Senior Developer" in agent.role), None)
    junior_dev = next((agent for agent in crew_agents if "Junior Developer" in agent.role), None)

    if project_manager:
        sequential_tasks.append(Task(
            description=f"""Analyze and plan the task: {inputs.get('prompt')}.
                        Read and extract Docker Compose details from the project config file: {project_config}
                        Coordinate research tasks and provide final answers to user questions.
                        Supply Team with needed project Information.
                        {All_DETAILS}
                        COORDINATION PROCESS:
                        1. For simple questions, provide direct answers from your existing knowledge
                        2. You Do not use the following tools 'Git Tool, Docker Tool.' Create a project plan for the team to execute it. Use the delegate plan for the best Agent to execute the task.
                        3. You can only delegate too Code Researcher, Senior Developer , Junior Developer
                        4. Only use tools if you genuinely don't know the answer
                        4. If you need project-specific details you don't know, then check {project_config}                    
                        5. Provide a natural, conversational answer to the user's question
        
                        CRITICAL INSTRUCTIONS:
                        - NEVER return raw file contents, YAML, JSON, or technical dumps
                        - Interpret the information and explain it in human-friendly terms
                        - Coordinate research efforts and synthesize findings
                        - Be concise but informative
                        - Prioritize local knowledge over external sources
                        
                        After you create and post the project task you can deliver your final 
                        save your learned knowledge ,  and project details you made. Instructions here:    {learning_doc}
                        Delegate task to Docker Specialist to create the containers so our crew can start.
                        Deliver final answer  with the project details you have created.
        
            
            """,
            agent=project_manager,
            expected_output="A detailed project plan and task breakdown.",
            callback=custom_logger.log_step_callback if custom_logger else None
        ))

    if docker_specialist:
        sequential_tasks.append(Task(
            description=f"""Make sure testing docker is  for the current team / crew  so they can  start working on there task:  {inputs.get('prompt')} .
            Read and extract Docker_Details details from the project config file:  {project_config}          
            ** Task  **  Bring up Containers specified in Docker_Details.compose_file  supplied in {project_config}.
            Make sure any docker you bring up does not conflict with the current dockers running.  especially the ones configured in Docker-compose.yml/
            Do not make any changes too Docker-compose.yml.
            Make sure all test containers are running correctly using Docker_Details in {project_config}
            You use the docker tools manage containers. Instructions on using it is located /app/knowledge/internal_crew/agent_learning/docker_usage_guide.md
        
            *** IMPORTANT *** DO NOT MESS WITH Docker-compose.yml  OR ANY CONTAINERS IN IT!
            Make sure you use the compose file instructed in the Config: {project_config}
            
            All Details: {All_DETAILS}
            If the content of  {project_config}  does not have what you need, Deliver your final answer as the Project config does not have the details your looking for and explain what you are looking for.

            After successfully setting up the project with Docker Composer, 
            Give the team instructions on how to connect to it.
            
            
            
            After docker is up  and test containers are running do the following:
            1) Output container information and instructions on how to connect containers you made
            2) insure broken an un-used images  and containers are removed
            3) save any un-saved knowledge you have learned too /app/knowledge/internal_crew/agent_learning/self/docker_specialist.
            4) Deliver your final Answer as the docker details you provided
            

        
            """,
            agent=docker_specialist,
            expected_output="Output Docker Container details for testing",
            callback=custom_logger.log_step_callback if custom_logger else None
        ))

    if code_researcher:
        sequential_tasks.append(Task(
            description=f"""Research and analyze code requirements for: {inputs.get('prompt')}
           
            Wait till docker Specialist has completed setting up the containers.
            Connect to containers  and verify what is needed to handle the task.
            
        
            save your learned knowledge
            """,
            agent=code_researcher,
            expected_output="Technical analysis and code recommendations.",
            callback=custom_logger.log_step_callback if custom_logger else None
        ))

    if senior_dev:
        sequential_tasks.append(Task(
            description=f"""Implement solution for: {inputs.get('prompt')}
            Wait for the Docker specialist to bring up the containers.  ask him to let you know how to connect to it if you have any issues.   
            To get the project details, you MUST use the FileReadTool on the file: {project_config}
            
            Find all information you need regarding your task in project_config = {project_config}. 
           
            e.g., `/app/knowledge/internal_crew/tool_usage_guide.md`.
            All Details: {All_DETAILS}
            If the content of  {project_config}  does not have what you need Deliver your final answer as the Project config does not have the details your looking for and explain what you are looking for.
            
            Create a plan for this task , based on the code researchers Response.
            Deliver it to the jr Developer for execution
            
            use the `Learning Tool` to save the successful configuration steps and findings. 
            
        
            **CRITICAL INSTRUCTION:** Read the project config file located at `/app/knowledge/internal_crew/cyford/zeroai/project_config.yaml`.
            save your learned knowledge
            """,
            agent=senior_dev,
            expected_output="Complete implementation with code and documentation.",
            callback=custom_logger.log_step_callback if custom_logger else None
        ))

    if junior_dev:
        sequential_tasks.append(Task(
            description=f"""Execute solution provided by the Senior Developer for: {inputs.get('prompt')}
            Read and extract details you need from the project config file: {project_config}
            Execute the plan made from the Sr. developer.
            
            Accurately find information in project files using ONLY relative paths with the FileReadTool. 

            All Details: {All_DETAILS}
            If the content of  {project_config}  does not have what you need Deliver your final answer as the Project config does not have the details your looking for and explain what you are looking for.
        
            **CRITICAL INSTRUCTION:** Read the project config file located at `/app/knowledge/internal_crew/cyford/zeroai/project_config.yaml` 
            to get the specific Docker Compose instructions before taking any action.
            save your learned knowledge
            """,
            agent=junior_dev,
            expected_output="Complete implementation with code and documentation.",
            callback=custom_logger.log_step_callback if custom_logger else None
        ))

    # Fallback logic for tasks (your existing logic)
    if not sequential_tasks and crew_agents:
        sequential_tasks = [Task(
            description=f"""{inputs.get('prompt')} {All_DETAILS}
            Read and extract Docker Compose details from the project config file: {project_config}.  Find all information you need regarding your task in {project_config}. 
            If the content of  {project_config}  does not have what you need Deliver your final answer as the Project config does not have the details your looking for and explain what you are looking for.
            """,
            agent=crew_agents,
            expected_output="Complete solution to the user's request.",
            callback=custom_logger.log_step_callback if custom_logger else None
        )]



    # common_knowledge = get_common_knowledge(
    #     project_location=project_id,
    #     repository=repository
    # )


    # Create a knowledge source from web content
    # content_source = CrewDoclingSource(
    #     file_paths=[
    #         "https://cyfordtechnologies.com/",
    #         "https://github.com/Cyford-Technologies-LLC/ZeroAI/",
    #     ],
    # )
    # # Create an LLM with a temperature of 0 to ensure deterministic outputs
    # llm = LLM(model="gpt-4o-mini", temperature=0)

    # Define the embedder as a dictionary for both Crew and Knowledge
    # NOTE: The 'base_url' is removed here to rely on the OLLAMA_HOST environment variable.
    crew_embedder_config = {
        "provider": "ollama",
        "config": {
            "model": "mxbai-embed-large",
            "base_url": "http:// gpu-001:11434/api/embeddings"
        }
    }

    # Attach knowledge to agents using the embedder dictionary
    # for agent in all_coworkers:
    #     agent.knowledge = Knowledge(
    #         sources=common_knowledge,
    #         embedder=crew_embedder_config,  # <-- Pass the dictionary here
    #         collection_name=f"crew_knowledge_{project_id}"
    #     )

    crew1 = Crew(
        agents=crew_agents,
        tasks=sequential_tasks,
        process=Process.sequential,
        verbose=True,
        full_output=full_output,
        # knowledge_sources=[content_source],
        # embedder={
        #     "provider": "ollama",  # Recommended for Claude users
        #     "config": {
        #         "model": "nomic-embed-text",  # or "voyage-3-large" for best quality
        #         "base_url": "http:// gpu-001:11434/api/embeddings"
        #     }
        # }

        # embedder=crew_embedder_config,  # <-- Pass the dictionary here
    )

    return crew1

