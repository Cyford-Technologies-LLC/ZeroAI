"""Database models for ZeroAI dynamic configuration system."""

import sqlite3
from pathlib import Path
from typing import Dict, Any, List, Optional
import json
import time

class ZeroAIDatabase:
    def __init__(self, db_path: str = "data/zeroai.db"):
        self.db_path = Path(db_path)
        self.db_path.parent.mkdir(parents=True, exist_ok=True)
        self.init_database()
    
    def get_connection(self):
        return sqlite3.connect(self.db_path)
    
    def init_database(self):
        with self.get_connection() as conn:
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
                
                CREATE TABLE IF NOT EXISTS knowledge (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    content TEXT NOT NULL,
                    type TEXT DEFAULT 'text',
                    access_level TEXT DEFAULT 'all',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
                
                CREATE TABLE IF NOT EXISTS crew_agents (
                    crew_id INTEGER,
                    agent_id INTEGER,
                    FOREIGN KEY (crew_id) REFERENCES crews (id),
                    FOREIGN KEY (agent_id) REFERENCES agents (id),
                    PRIMARY KEY (crew_id, agent_id)
                );
                
                CREATE TABLE IF NOT EXISTS knowledge_access (
                    knowledge_id INTEGER,
                    entity_type TEXT,
                    entity_id INTEGER,
                    FOREIGN KEY (knowledge_id) REFERENCES knowledge (id)
                );
            """)
    
    def create_core_agents(self):
        """Create core agents that cannot be removed."""
        core_agents = [
            {
                "name": "Team Manager",
                "role": "Team Coordination Specialist",
                "goal": "Coordinate and manage team activities efficiently",
                "backstory": "Expert in team management and workflow optimization",
                "config": json.dumps({"tools": ["delegate_tool", "scheduling_tool"], "memory": True}),
                "is_core": True
            },
            {
                "name": "Project Manager", 
                "role": "Project Management Expert",
                "goal": "Oversee project execution and ensure deliverables",
                "backstory": "Experienced project manager with technical background",
                "config": json.dumps({"tools": ["file_tool", "git_tool"], "memory": True}),
                "is_core": True
            },
            {
                "name": "Prompt Refinement Agent",
                "role": "Prompt Optimization Specialist", 
                "goal": "Refine and optimize prompts for better AI responses",
                "backstory": "Expert in natural language processing and prompt engineering",
                "config": json.dumps({"tools": ["learning_tool"], "memory": True}),
                "is_core": True
            }
        ]
        
        with self.get_connection() as conn:
            for agent in core_agents:
                conn.execute("""
                    INSERT OR IGNORE INTO agents (name, role, goal, backstory, config, is_core)
                    VALUES (?, ?, ?, ?, ?, ?)
                """, (agent["name"], agent["role"], agent["goal"], agent["backstory"], agent["config"], agent["is_core"]))