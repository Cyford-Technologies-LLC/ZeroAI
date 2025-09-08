#!/usr/bin/env python3
"""Minimal database setup script."""

import sqlite3
import json
from pathlib import Path

def setup_database():
    """Setup ZeroAI database with minimal dependencies."""
    db_path = Path("data/zeroai.db")
    db_path.parent.mkdir(parents=True, exist_ok=True)
    
    conn = sqlite3.connect(db_path)
    
    # Create tables
    conn.executescript("""
        CREATE TABLE IF NOT EXISTS agents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            role TEXT NOT NULL,
            goal TEXT NOT NULL,
            backstory TEXT NOT NULL,
            config TEXT NOT NULL,
            is_core BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS crews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            description TEXT,
            process_type TEXT DEFAULT 'sequential',
            config TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            description TEXT,
            repository TEXT,
            default_branch TEXT DEFAULT 'main',
            config TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    """)
    
    # Create core agents
    core_agents = [
        ("Team Manager", "Team Coordination Specialist", "Coordinate team activities", "Expert in team management", '{"tools": ["delegate_tool"], "memory": true}', 1),
        ("Project Manager", "Project Management Expert", "Oversee project execution", "Experienced project manager", '{"tools": ["file_tool"], "memory": true}', 1),
        ("Prompt Refinement Agent", "Prompt Optimization Specialist", "Refine prompts for better responses", "Expert in prompt engineering", '{"tools": ["learning_tool"], "memory": true}', 1)
    ]
    
    for agent in core_agents:
        conn.execute("""
            INSERT OR IGNORE INTO agents (name, role, goal, backstory, config, is_core)
            VALUES (?, ?, ?, ?, ?, ?)
        """, agent)
    
    # Create default project
    conn.execute("""
        INSERT OR IGNORE INTO projects (name, description, repository, config)
        VALUES (?, ?, ?, ?)
    """, ("default", "Default ZeroAI project", None, "{}"))
    
    conn.commit()
    conn.close()
    
    print("Database setup complete!")
    print(f"Database created at: {db_path.absolute()}")

if __name__ == "__main__":
    setup_database()