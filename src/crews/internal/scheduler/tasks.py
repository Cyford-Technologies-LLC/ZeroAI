# src/crews/internal/scheduler/tasks.py

from crewai import Task, Agent
from typing import Dict, Any

def schedule_management_task(agent: Agent, inputs: Dict[str, Any]) -> Task:
    topic = inputs.get('topic', 'schedule management')
    
    return Task(
        description=f"""
        Manage schedules, appointments, and calendar events.
        Task context: {topic}
        
        SCHEDULING TASKS:
        1. Analyze scheduling requests and requirements
        2. Check for conflicts and availability
        3. Create and organize calendar events
        4. Manage timelines and deadlines
        5. Coordinate meeting schedules
        6. Provide scheduling recommendations
        
        Focus on efficient calendar management and time organization.
        """,
        agent=agent,
        expected_output="A comprehensive schedule management plan with organized events, timelines, and recommendations."
    )