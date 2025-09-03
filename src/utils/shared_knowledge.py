# src/utils/shared_knowledge.py
from pathlib import Path
from typing import Dict, Any
import os

def load_team_briefing() -> str:
    """Load the shared team briefing that all agents should read."""
    briefing_path = Path("knowledge/internal_crew/agent_learning/team_briefing.md")
    
    if briefing_path.exists():
        try:
            return briefing_path.read_text(encoding='utf-8')
        except Exception as e:
            return f"Error loading team briefing: {e}"
    else:
        return "Team briefing not found. Operating without shared context."

def get_agent_learning_path(agent_role: str) -> Path:
    """Get the learning directory path for a specific agent."""
    safe_role = agent_role.replace(" ", "_").replace("/", "_").lower()
    learning_path = Path(f"knowledge/internal_crew/agent_learning/{safe_role}")
    learning_path.mkdir(parents=True, exist_ok=True)
    return learning_path

def save_agent_learning(agent_role: str, filename: str, content: str) -> bool:
    """Save learning content for an agent."""
    try:
        learning_path = get_agent_learning_path(agent_role)
        file_path = learning_path / filename
        file_path.write_text(content, encoding='utf-8')
        return True
    except Exception:
        return False

def get_shared_context_for_agent(agent_role: str) -> str:
    """Get complete shared context including team briefing and agent-specific learning."""
    context = f"# Shared Team Knowledge\n\n{load_team_briefing()}\n\n"
    
    # Add agent-specific learning path info
    learning_path = get_agent_learning_path(agent_role)
    context += f"## Your Learning Directory\n"
    context += f"Store your discoveries in: `{learning_path}`\n"
    context += f"Use the format: `YYYY-MM-DD_discovery_name.md`\n\n"
    
    return context