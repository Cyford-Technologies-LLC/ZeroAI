"""Database management agents for ZeroAI internal operations."""

from crewai import Agent
from src.database.models import ZeroAIDatabase

def create_db_admin_agent():
    """Create database administration agent."""
    return Agent(
        role="Database Administrator",
        goal="Manage and maintain ZeroAI database operations efficiently",
        backstory="Expert database administrator responsible for data integrity and system configuration management",
        verbose=True,
        allow_delegation=False
    )

def create_config_migration_agent():
    """Create configuration migration agent."""
    return Agent(
        role="Configuration Migration Specialist", 
        goal="Migrate static configurations to database-driven architecture",
        backstory="Specialist in transforming legacy configuration systems to modern database-driven approaches",
        verbose=True,
        allow_delegation=False
    )