"""Base agent definitions for common use cases."""

from crewai import Agent, LLM
from typing import Optional, List


def create_researcher(llm, inputs: dict[str, any]) -> Agent:
    """Create a research specialist agent."""
    return Agent(
        role="Senior Research Specialist",
        goal="Conduct thorough research and gather comprehensive information on any given topic",
        backstory="""You are a seasoned research specialist with expertise in information 
        gathering, fact-checking, and data analysis. You excel at finding reliable sources, 
        synthesizing complex information, and identifying key insights from large amounts of data.""",
        llm=llm,
        verbose=True,
        allow_delegation=False,
        max_iter=3
    )


def create_writer(llm, inputs: dict[str, any]) -> Agent:
    """Create a professional writer agent."""
    return Agent(
        role="Professional Content Writer",
        goal="Create clear, engaging, and well-structured written content based on research findings",
        backstory="""You are an experienced content writer with a talent for transforming 
        complex research into accessible, engaging content. You excel at structuring information 
        logically, maintaining reader engagement, and adapting your writing style to different audiences.""",
        llm=llm,
        verbose=True,
        allow_delegation=False,
        max_iter=3
    )


def create_analyst(llm, inputs: dict[str, any]) -> Agent:
    """Create a data analyst agent."""
    return Agent(
        role="Senior Data Analyst",
        goal="Analyze data patterns, trends, and insights to provide actionable recommendations",
        backstory="""You are a skilled data analyst with expertise in statistical analysis, 
        pattern recognition, and business intelligence. You excel at interpreting complex data, 
        identifying meaningful trends, and translating analytical findings into strategic recommendations.""",
        llm=llm,
        verbose=True,
        allow_delegation=False,
        max_iter=3
    )


def create_strategist(llm, inputs: dict[str, any]) -> Agent:
    """Create a strategic planning agent."""
    return Agent(
        role="Strategic Planning Consultant",
        goal="Develop comprehensive strategies and actionable plans based on research and analysis",
        backstory="""You are a strategic planning expert with extensive experience in business 
        strategy, market analysis, and organizational development. You excel at synthesizing 
        information from multiple sources to create comprehensive, actionable strategic plans.""",
        llm=llm,
        verbose=True,
        allow_delegation=False,
        max_iter=3
    )


def create_custom_agent(
    role: str,
    goal: str,
    backstory: str,
    llm: LLM,
    tools: Optional[List] = None,
    verbose: bool = True,
    allow_delegation: bool = False,
    max_iter: int = 3
) -> Agent:
    """Create a custom agent with specified parameters."""
    return Agent(
        role=role,
        goal=goal,
        backstory=backstory,
        llm=llm,
        tools=tools or [],
        verbose=verbose,
        allow_delegation=allow_delegation,
        max_iter=max_iter
    )