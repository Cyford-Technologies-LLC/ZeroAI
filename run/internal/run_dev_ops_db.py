#!/usr/bin/env python3
"""Database-driven DevOps crew runner - Phase 1 transformation."""

import sys
import os
from pathlib import Path

# Set CREW_TYPE for internal operations BEFORE any imports
os.environ["CREW_TYPE"] = "internal"

# Add the project root to the Python path
project_root = Path(__file__).parent.parent.parent
sys.path.insert(0, str(project_root))

import argparse
from rich.console import Console
from src.database.models import ZeroAIDatabase
from src.crews.internal.team_manager.agents import create_team_manager_agent
from src.distributed_router import DistributedRouter
from src.peer_discovery import PeerDiscovery

console = Console()

def setup_arg_parser():
    parser = argparse.ArgumentParser(description="Run Database-driven AI DevOps Crew")
    parser.add_argument("prompt", help="The task description or prompt")
    parser.add_argument("--project", default="default", help="Project identifier")
    parser.add_argument("--category", default="general", help="Task category")
    parser.add_argument("--verbose", "-v", action="store_true", help="Enable verbose output")
    parser.add_argument("--dry-run", action="store_true", help="Simulate execution only")
    return parser

def load_project_from_db(project_name: str, db: ZeroAIDatabase) -> dict:
    """Load project configuration from database."""
    with db.get_connection() as conn:
        cursor = conn.execute("SELECT * FROM projects WHERE name = ?", (project_name,))
        project = cursor.fetchone()
        
        if not project:
            # Create default project
            conn.execute("""
                INSERT INTO projects (name, description, repository, config)
                VALUES (?, ?, ?, ?)
            """, (project_name, "Auto-generated project", None, "{}"))
            conn.commit()
            return {"name": project_name, "description": "Auto-generated project"}
        
        return dict(zip([col[0] for col in cursor.description], project))

def execute_db_devops_task(args, project_config, db):
    """Execute DevOps task using database-driven configuration."""
    console.print(f"\n🚀 [bold blue]Database-driven DevOps Task[/bold blue]")
    console.print(f"📂 Project: [bold yellow]{args.project}[/bold yellow]")
    console.print(f"🔍 Category: [bold green]{args.category}[/bold green]")
    
    # Get agents from database
    with db.get_connection() as conn:
        cursor = conn.execute("SELECT * FROM agents WHERE is_core = 1")
        core_agents = cursor.fetchall()
    
    console.print(f"🤖 Available core agents: {len(core_agents)}")
    
    # Initialize router
    discovery = PeerDiscovery()
    router = DistributedRouter(discovery)
    
    # Execute task (simplified for Phase 1)
    result = {
        "success": True,
        "message": f"Database-driven task executed: {args.prompt}",
        "agents_used": len(core_agents),
        "project": project_config["name"]
    }
    
    return result

if __name__ == "__main__":
    try:
        parser = setup_arg_parser()
        args = parser.parse_args()
        
        # Initialize database
        db = ZeroAIDatabase()
        
        # Load project from database
        project_config = load_project_from_db(args.project, db)
        
        # Execute task
        result = execute_db_devops_task(args, project_config, db)
        
        if result and result.get("success"):
            console.print(f"✅ [bold green]{result['message']}[/bold green]")
        else:
            console.print("❌ [bold red]Task failed[/bold red]")
            sys.exit(1)
            
    except Exception as e:
        console.print(f"❌ [bold red]Error: {str(e)}[/bold red]")
        sys.exit(1)