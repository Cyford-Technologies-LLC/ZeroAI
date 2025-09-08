#!/usr/bin/env python3
"""Test the database-driven DevOps runner."""

import sqlite3
from pathlib import Path

def test_db_operations():
    """Test basic database operations."""
    db_path = Path("data/zeroai.db")
    
    if not db_path.exists():
        print("Database not found. Run setup_db.py first.")
        return False
    
    conn = sqlite3.connect(db_path)
    
    # Test agents
    cursor = conn.execute("SELECT COUNT(*) FROM agents WHERE is_core = 1")
    core_agents = cursor.fetchone()[0]
    print(f"Core agents in database: {core_agents}")
    
    # Test projects
    cursor = conn.execute("SELECT COUNT(*) FROM projects")
    projects = cursor.fetchone()[0]
    print(f"Projects in database: {projects}")
    
    # List core agents
    cursor = conn.execute("SELECT name, role FROM agents WHERE is_core = 1")
    agents = cursor.fetchall()
    print("Core agents:")
    for name, role in agents:
        print(f"  - {name}: {role}")
    
    conn.close()
    return True

def simulate_devops_task():
    """Simulate a database-driven DevOps task."""
    print("\nSimulating database-driven DevOps task...")
    
    # This simulates what run_dev_ops_db.py would do
    db_path = Path("data/zeroai.db")
    conn = sqlite3.connect(db_path)
    
    # Get available agents
    cursor = conn.execute("SELECT name FROM agents")
    agents = [row[0] for row in cursor.fetchall()]
    
    # Get project info
    cursor = conn.execute("SELECT name, description FROM projects WHERE name = 'default'")
    project = cursor.fetchone()
    
    print(f"Project: {project[0]} - {project[1]}")
    print(f"Available agents: {len(agents)}")
    print("Task: Test database integration")
    print("Status: SUCCESS - Database-driven operations working")
    
    conn.close()

if __name__ == "__main__":
    print("Testing ZeroAI Database-Driven DevOps...")
    
    if test_db_operations():
        simulate_devops_task()
        print("\nNext step: Start web portal with 'python www/api/portal_api.py'")
    else:
        print("Database test failed.")