# src/crews/internal/diagnostics/tasks.py
from crewai import Task
from typing import Dict, Any

def create_diagnostics_task(agent, inputs: Dict[str, Any]) -> Task:
    """Create a diagnostics task for analyzing system issues."""
    
    log_output = inputs.get("log_output", "")
    error_context = inputs.get("error_context", "")
    
    return Task(
        description=f"""
        Perform comprehensive system diagnostics:
        
        1. Use the 'Diagnostic File Handler Tool' to process and consolidate any error files from previous runs
        2. Use the 'Task Queue Monitor Tool' to check for failed tasks in the queue
        3. Use the 'Log Analysis Tool' to analyze verbose logs and identify delegation failures
        4. Provide actionable recommendations for fixing identified issues
        
        Context: {error_context}
        Logs to analyze: {log_output}
        """,
        agent=agent,
        expected_output="A comprehensive diagnostic report with identified issues, root causes, and specific recommendations for fixes."
    )

def create_error_analysis_task(agent, inputs: Dict[str, Any]) -> Task:
    """Create a task specifically for analyzing error logs."""
    
    return Task(
        description=f"""
        Analyze system errors and provide solutions:
        
        1. Process error files using the 'Diagnostic File Handler Tool'
        2. Identify patterns in errors and failures
        3. Recommend specific fixes for each type of error
        4. Archive processed errors using the 'Task Manager Logger Tool'
        
        Focus on: {inputs.get('focus_area', 'general system health')}
        """,
        agent=agent,
        expected_output="Detailed error analysis with categorized issues and step-by-step fix recommendations."
    )