# src/crews/internal/developer/agents.py

from crewai import Agent
from typing import Dict, Any
from utils.memory import Memory


# Create memory instance
memory = Memory()





def create_code_researcher(router=None, inputs: Dict[str, Any] = None) -> Agent:
    """Create a Code Researcher agent."""
    # Model preference order: codellama:13b -> llama3.1:8b -> llama3.2:latest -> llama3.2:1b
    preferred_models = ["codellama:13b", "llama3.1:8b", "llama3.2:latest", "llama3.2:1b"]

    # Check if we have learning-based model preference
    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference("developer")
        if category_model:
            # Add the learning-preferred model to the top of the list
            if category_model not in preferred_models:
                preferred_models.insert(0, category_model)
    except ImportError:
        pass  # Learning module not available

    return Agent(
        role="Code Researcher",
        name="Dr. Alan Parse",
        memory=memory,  # Add memory here
        learning={
                "enabled": True,
                "learning_rate": 0.05,
                "feedback_incorporation": "immediate",
                "adaptation_strategy": "progressive"
            },
        personality={
                "traits": ["analytical", "detail-oriented", "methodical"],
                "quirks": ["always cites research papers", "uses scientific analogies"],
                "communication_preferences": ["prefers direct questions", "responds with examples"]
            },
        communication_style={
                "formality": "professional",
                "verbosity": "concise",
                "tone": "authoritative",
                "technical_level": "expert"
            },
        resources=[
                "testing_frameworks.md",
                "code_quality_guidelines.pdf",
                "https://testing-best-practices.com"
            ],
        expertise=[
                "Python", "JavaScript", "Database Design",
                "API Development", "Microservices Architecture","PHP","JavaScript"
            ],
        expertise_level=9.2,  # On a scale of 1-10

        goal="Research and understand code patterns and issues",
        backstory="""You are an expert at analyzing codebases, understanding
        complex systems, and identifying potential issues.""",
        verbose=True,
        llm=router.route_task("code research", preferred_models=preferred_models) if router else None,
        allow_delegation=False
    )

def create_senior_developer(router=None, inputs: Dict[str, Any] = None) -> Agent:
    """Create a Senior Developer agent."""
    # Model preference order: codellama:13b -> llama3.1:8b -> llama3.2:latest -> llama3.2:1b
    preferred_models = ["codellama:13b", "llama3.1:8b", "llama3.2:latest", "llama3.2:1b"]

    # Try to get learning-based model preference
    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference("developer")
        if category_model:
            if category_model not in preferred_models:
                preferred_models.insert(0, category_model)
    except ImportError:
        pass

    return Agent(
        role="Senior Developer",
        name="Tony Kyles",
        memory=memory,  # Add memory here
        learning={
                "enabled": True,
                "learning_rate": 0.05,
                "feedback_incorporation": "immediate",
                "adaptation_strategy": "progressive"
            },
        personality={
                "traits": ["analytical", "detail-oriented", "methodical"],
                "quirks": ["always cites research papers", "uses scientific analogies"],
                "communication_preferences": ["prefers direct questions", "responds with examples"]
            },
        communication_style={
                "formality": "professional",
                "verbosity": "concise",
                "tone": "authoritative",
                "technical_level": "expert"
            },
        resources=[
                "testing_frameworks.md",
                "code_quality_guidelines.pdf",
                "https://testing-best-practices.com"
            ],
        goal="Implement high-quality code solutions",
        backstory="""You are a skilled software developer with years of experience.
        You create elegant, maintainable, and robust code solutions to complex problems.""",
        verbose=True,
        llm=router.route_task("code development", preferred_models=preferred_models) if router else None,
        allow_delegation=False
    )

def create_qa_engineer(router=None, inputs: Dict[str, Any] = None) -> Agent:
    """Create a QA Engineer agent."""
    # Model preference order: codellama:13b -> llama3.1:8b -> llama3.2:latest -> llama3.2:1b
    preferred_models = ["codellama:13b", "llama3.1:8b", "llama3.2:latest", "llama3.2:1b"]

    # Try to get learning-based model preference
    try:
        from learning.feedback_loop import feedback_loop
        category_model = feedback_loop.get_model_preference("qa")
        if category_model:
            if category_model not in preferred_models:
                preferred_models.insert(0, category_model)
    except ImportError:
        pass

    return Agent(
        role="QA Engineer",
        name="Anthony Gates",
        memory=memory,  # Add memory here
        learning={
                "enabled": True,
                "learning_rate": 0.05,
                "feedback_incorporation": "immediate",
                "adaptation_strategy": "progressive"
            },
        personality={
                "traits": ["analytical", "detail-oriented", "methodical"],
                "quirks": ["always cites research papers", "uses scientific analogies"],
                "communication_preferences": ["prefers direct questions", "responds with examples"]
            },
        communication_style={
                "formality": "professional",
                "verbosity": "concise",
                "tone": "authoritative",
                "technical_level": "expert"
            },
        resources=[
                "testing_frameworks.md",
                "code_quality_guidelines.pdf",
                "https://testing-best-practices.com"
            ],
        goal="Ensure code quality and functionality",
        backstory="""You are a meticulous quality assurance engineer who takes pride
        in finding edge cases and ensuring robust software.""",
        verbose=True,
        llm=router.route_task("code testing", preferred_models=preferred_models) if router else None,
        allow_delegation=False
    )