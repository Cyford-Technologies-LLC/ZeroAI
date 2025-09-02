# src/crews/internal/research/tasks.py

from crewai import Task, Agent
from typing import Dict, Any

def internal_research_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    working_dir = inputs.get('working_directory', inputs.get('working_dir', 'unknown'))
    topic = inputs.get('topic', 'general project research')
    
    return Task(
        description=f"""
        Perform focused internal research for the project.
        Working directory: {working_dir}
        Research topic: {topic}
        
        Tasks:
        1. Analyze project structure and configuration files
        2. Review documentation and README files
        3. Identify key project components and dependencies
        4. Document findings in a structured format
        """,
        agent=agent,
        expected_output="A comprehensive research report with project details, structure analysis, and key findings."
    )

def internal_analysis_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    topic = inputs.get('topic', 'project analysis')
    
    return Task(
        description=f"""
        Analyze the research findings and provide actionable insights.
        Project context: {topic}
        
        Tasks:
        1. Review the research report from the internal researcher
        2. Identify key patterns and insights
        3. Highlight potential issues or opportunities
        4. Provide recommendations for next steps
        """,
        agent=agent,
        expected_output="An analytical summary with key insights, recommendations, and actionable next steps based on the research findings."
    )

def project_management_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    user_question = inputs.get('topic', inputs.get('question', 'general project inquiry'))
    
    return Task(
        description=f"""
        Answer the user's question about the project in a conversational, helpful manner.
        User question: {user_question}
        
        STEP-BY-STEP PROCESS:
        1. FIRST: Check local knowledge files using File Tool:
           - Read knowledge/internal_crew/cyford/zeroai/project_config.yaml
           - Check other files in knowledge/ directory if needed
        2. Use your memory to recall previously learned information
        3. ONLY if local files don't have the answer, then consider other tools
        4. Provide a natural, conversational answer to the user's question
        
        CRITICAL INSTRUCTIONS:
        - NEVER return raw file contents, YAML, JSON, or technical dumps
        - Interpret the information and explain it in human-friendly terms
        - If asking about a company, explain what they do and their projects
        - Be concise but informative
        - Prioritize local knowledge over external sources
        
        Remember: You are having a conversation with a human, not providing a data dump.
        """,
        agent=agent,
        expected_output="A complete answer to the user's request with accurate project information."
    )
