#!/usr/bin/env python3
"""
Token and Cost Tracking Database
Tracks token usage and costs for agents and cloud AI services
"""

import sqlite3
import datetime
from pathlib import Path
from typing import Optional, Dict, List

class TokenTracker:
    def __init__(self, db_path: str = "/app/data/zeroai.db"):
        self.db_path = db_path
        self.init_database()
    
    def init_database(self):
        """Initialize the token tracking table"""
        Path(self.db_path).parent.mkdir(parents=True, exist_ok=True)
        
        with sqlite3.connect(self.db_path) as conn:
            conn.execute("""
                CREATE TABLE IF NOT EXISTS token_usage (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                    service_type TEXT NOT NULL,  -- 'agent', 'claude', 'openai', 'local'
                    service_name TEXT NOT NULL,  -- agent name or AI service
                    model_name TEXT,             -- model used
                    input_tokens INTEGER DEFAULT 0,
                    output_tokens INTEGER DEFAULT 0,
                    total_tokens INTEGER DEFAULT 0,
                    cost_usd DECIMAL(10,6) DEFAULT 0.0,
                    task_id TEXT,               -- link to specific task
                    project_id TEXT,            -- project context
                    user_id TEXT DEFAULT 'system'
                )
            """)
            
            # Create indexes for better performance
            conn.execute("CREATE INDEX IF NOT EXISTS idx_timestamp ON token_usage(timestamp)")
            conn.execute("CREATE INDEX IF NOT EXISTS idx_service ON token_usage(service_type, service_name)")
            conn.execute("CREATE INDEX IF NOT EXISTS idx_task ON token_usage(task_id)")
    
    def log_usage(self, service_type: str, service_name: str, 
                  input_tokens: int = 0, output_tokens: int = 0,
                  model_name: str = None, cost_usd: float = 0.0,
                  task_id: str = None, project_id: str = None, user_id: str = "system"):
        """Log token usage and cost"""
        total_tokens = input_tokens + output_tokens
        
        with sqlite3.connect(self.db_path) as conn:
            conn.execute("""
                INSERT INTO token_usage 
                (service_type, service_name, model_name, input_tokens, output_tokens, 
                 total_tokens, cost_usd, task_id, project_id, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (service_type, service_name, model_name, input_tokens, output_tokens,
                  total_tokens, cost_usd, task_id, project_id, user_id))
    
    def get_usage_summary(self, days: int = 30) -> Dict:
        """Get usage summary for the last N days"""
        with sqlite3.connect(self.db_path) as conn:
            conn.row_factory = sqlite3.Row
            
            # Total usage by service type
            cursor = conn.execute("""
                SELECT service_type, 
                       COUNT(*) as requests,
                       SUM(total_tokens) as total_tokens,
                       SUM(cost_usd) as total_cost
                FROM token_usage 
                WHERE timestamp >= datetime('now', '-{} days')
                GROUP BY service_type
            """.format(days))
            
            summary = {
                'by_service': [dict(row) for row in cursor.fetchall()],
                'period_days': days
            }
            
            # Top services by cost
            cursor = conn.execute("""
                SELECT service_name, model_name,
                       COUNT(*) as requests,
                       SUM(total_tokens) as total_tokens,
                       SUM(cost_usd) as total_cost
                FROM token_usage 
                WHERE timestamp >= datetime('now', '-{} days')
                GROUP BY service_name, model_name
                ORDER BY total_cost DESC
                LIMIT 10
            """.format(days))
            
            summary['top_services'] = [dict(row) for row in cursor.fetchall()]
            
            return summary
    
    def get_daily_costs(self, days: int = 7) -> List[Dict]:
        """Get daily cost breakdown"""
        with sqlite3.connect(self.db_path) as conn:
            conn.row_factory = sqlite3.Row
            
            cursor = conn.execute("""
                SELECT DATE(timestamp) as date,
                       service_type,
                       SUM(total_tokens) as tokens,
                       SUM(cost_usd) as cost
                FROM token_usage 
                WHERE timestamp >= datetime('now', '-{} days')
                GROUP BY DATE(timestamp), service_type
                ORDER BY date DESC, service_type
            """.format(days))
            
            return [dict(row) for row in cursor.fetchall()]

# Pricing constants (per 1K tokens)
PRICING = {
    'claude-3-5-sonnet-20241022': {'input': 0.003, 'output': 0.015},
    'claude-3-haiku-20240307': {'input': 0.00025, 'output': 0.00125},
    'gpt-4': {'input': 0.03, 'output': 0.06},
    'gpt-3.5-turbo': {'input': 0.001, 'output': 0.002},
    'local': {'input': 0.0, 'output': 0.0}  # Local models are free
}

def calculate_cost(model_name: str, input_tokens: int, output_tokens: int) -> float:
    """Calculate cost based on model pricing"""
    if model_name not in PRICING:
        return 0.0
    
    pricing = PRICING[model_name]
    input_cost = (input_tokens / 1000) * pricing['input']
    output_cost = (output_tokens / 1000) * pricing['output']
    
    return input_cost + output_cost

# Global tracker instance
tracker = TokenTracker()