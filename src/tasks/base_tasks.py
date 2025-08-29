"""Base task definitions for common workflows."""

from crewai import Task, Agent
from typing import Optional, Dict, Any


def create_research_task(agent: Agent, inputs: Dict[str, Any], context: Optional[list[Task]] = None) -> Task:
    """Create a comprehensive research task."""
    return Task(
        description="""Conduct thorough research on the given topic: {topic}
        
        Your research should include:
        1. Key facts and current information
        2. Historical context and background
        3. Current trends and developments
        4. Different perspectives and viewpoints
        5. Reliable sources and references
        
        Provide a comprehensive research summary that covers all important aspects of the topic.""",
        agent=agent,
        context=context,
        expected_output="A detailed research report with key findings, sources, and comprehensive coverage of the topic."
    )


def create_writing_task(agent: Agent, inputs: Dict[str, Any], context: Optional[list[Task]] = None) -> Task:
    """Create a professional writing task."""
    return Task(
        description=f"""Based on the research provided, create a well-structured, engaging article about: {inputs.get("topic")}.
        
        Your article should:
        1. Have a compelling introduction that hooks the reader
        2. Be organized with clear sections and logical flow
        3. Include key insights and important information
        4. Be written in an accessible, engaging style
        5. Have a strong conclusion that summarizes key points
        6. Be approximately 800-1200 words
        
        Focus on clarity, accuracy, and reader engagement.""",
        agent=agent,
        context=context,
        expected_output="A well-written, engaging article that effectively communicates the research findings to the target audience."
    )


def create_analysis_task(agent: Agent, inputs: Dict[str, Any], context: Optional[list[Task]] = None) -> Task:
    """Create a data analysis task."""
    return Task(
        description="""Analyze the research data and information about: {topic}
        
        Your analysis should include:
        1. Key patterns and trends identified
        2. Statistical insights and data interpretation
        3. Comparative analysis where relevant
        4. Risk assessment and opportunities
        5. Actionable recommendations
        6. Future projections and implications
        
        Provide clear, data-driven insights that can inform decision-making.""",
        agent=agent,
        context=context,
        expected_output="A comprehensive analytical report with data-driven insights, trends, and actionable recommendations."
    )


def create_strategy_task(agent: Agent, inputs: Dict[str, Any], context: Optional[list[Task]] = None) -> Task:
    """Create a strategic planning task."""
    return Task(
        description="""Develop a comprehensive strategy for: {topic}
        
        Your strategy should include:
        1. Situation analysis and current state assessment
        2. Clear objectives and goals
        3. Strategic options and recommendations
        4. Implementation roadmap with timelines
        5. Resource requirements and considerations
        6. Risk mitigation strategies
        7. Success metrics and KPIs
        
        Create a practical, actionable strategic plan.""",
        agent=agent,
        context=context,
        expected_output="A detailed strategic plan with clear objectives, actionable steps, timelines, and success metrics."
    )


def create_custom_task(
    description: str,
    agent: Agent,
    expected_output: str,
    context: Optional[str] = None,
    tools: Optional[list] = None
) -> Task:
    """Create a custom task with specified parameters."""
    return Task(
        description=description,
        agent=agent,
        expected_output=expected_output,
        context=context,
        tools=tools or []
    )