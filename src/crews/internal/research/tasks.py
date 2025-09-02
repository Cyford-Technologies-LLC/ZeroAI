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
