# src/crews/internal/research/tasks.py

from crewai import Task, Agent
from typing import Dict, Any


from rich.console import Console # Import the Console class

console = Console() # Instantiate the console object


def internal_research_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    working_dir = inputs.get('working_directory', inputs.get('working_dir', 'unknown'))
    topic = inputs.get('topic', 'general project research')
    project_id = inputs.get('project_id', 'unknown')
    project_location = f"knowledge/internal_crew/{project_id}"
    project_config = f"{project_location}/project_config.yaml"

    
    return Task(
        description=f"""
        Gather detailed information on internal project specifics.
        Working directory: {working_dir}
        Research topic: {topic}
        Project ID:   {project_id}
        Project location: {project_location}
        Project Config:  {project_config}

        
        INTERNAL RESEARCH TASKS:
        1. Read project configuration files from {project_location}/
        2. Analyze project structure and components
        3. Review internal documentation and README files
        4. Extract key project details, dependencies, and settings
        5. Document all findings in structured format
        
        Focus on internal project files and configurations.
        Sign off all responses with 'Internal Research Specialist'.
        """,
        agent=agent,
        expected_output="A comprehensive internal research report with project details, structure analysis, and key findings signed by Internal Research Specialist."
    )

def online_research_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    topic = inputs.get('topic', 'general online research')
    working_dir = inputs.get('working_directory', inputs.get('working_dir', 'unknown'))
    project_id = inputs.get('project_id', 'unknown')
    project_location = f"knowledge/internal_crew/{project_id}"
    project_config = f"{project_location}/project_config.yaml"
    return Task(
        description=f"""
        Perform comprehensive online searches to find external information.
        Research topic: {topic}
        
        Tasks:
        1. Search for relevant online information about the topic
        2. Find external documentation and resources
        3. Gather information from web sources
        4. Provide search results with source URLs
        
        Focus on external sources and web-based information.
        """,
        agent=agent,
        expected_output="Comprehensive online search results with source URLs and external information findings."
    )

def project_management_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    user_question = inputs.get('topic', inputs.get('question', 'general project inquiry'))
    project_location = inputs.get('project_id', 'unknown')
    working_dir = inputs.get('working_directory', inputs.get('working_dir', 'unknown'))
    topic = inputs.get('topic', 'general project research')
    project_id = inputs.get('project_id', 'unknown')
    project_location = f"knowledge/internal_crew/{project_id}"
    project_config = f"{project_location}/project_config.yaml"
    console.print(f"⚠️ Project Manager Task {project_config}  ", style="yellow")
    
    return Task(
        description=f"""
        Coordinate research tasks and provide final answers to user questions.
        User question: {user_question}
        Project location: {project_location}
        
        Working directory: {working_dir}
        Research topic: {topic}
        Project ID:   {project_id}
        Project location: {project_location}
        Project Config:  {project_config}

        
        COORDINATION PROCESS:
        1. For simple questions, provide direct answers from your existing knowledge
        2. The git URL is: https://github.com/Cyford-Technologies-LLC/ZeroAI.git
        3. Only use tools if you genuinely don't know the answer
        4. If you need project-specific details you don't know, then check knowledge/internal_crew/{project_location}/project_config.yaml
        5. Provide a natural, conversational answer to the user's question
        
        CRITICAL INSTRUCTIONS:
        - NEVER return raw file contents, YAML, JSON, or technical dumps
        - Interpret the information and explain it in human-friendly terms
        - Coordinate research efforts and synthesize findings
        - Be concise but informative
        - Prioritize local knowledge over external sources
        
        Remember: You are managing the research process and providing final answers.
        """,
        agent=agent,
        expected_output="A complete coordinated answer to the user's request with accurate project information."
    )
