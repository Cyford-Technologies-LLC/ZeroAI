"""
Dynamic Agent Loader - Loads agents from database instead of static files
"""
import sqlite3
import os
from typing import Dict, Any, List, Optional
from crewai import Agent, LLM
from rich.console import Console

console = Console()

class DynamicAgentLoader:
    def __init__(self, db_path: str = "/app/data/agents.db"):
        self.db_path = db_path
        self.ensure_db_exists()
    
    def ensure_db_exists(self):
        """Ensure database exists, create if not"""
        if not os.path.exists(self.db_path):
            os.makedirs(os.path.dirname(self.db_path), exist_ok=True)
            # Database will be created by PHP first
    
    def get_agent_config(self, agent_id: int = None, role: str = None) -> Optional[Dict[str, Any]]:
        """Get agent configuration from database"""
        try:
            conn = sqlite3.connect(self.db_path)
            conn.row_factory = sqlite3.Row
            cursor = conn.cursor()
            
            if agent_id:
                cursor.execute("SELECT * FROM agents WHERE id = ? AND status = 'active'", (agent_id,))
            elif role:
                cursor.execute("SELECT * FROM agents WHERE role = ? AND status = 'active'", (role,))
            else:
                return None
                
            row = cursor.fetchone()
            if not row:
                return None
                
            # Convert to dict and get capabilities
            agent_config = dict(row)
            cursor.execute("SELECT capability FROM agent_capabilities WHERE agent_id = ?", (agent_config['id'],))
            capabilities = [cap[0] for cap in cursor.fetchall()]
            agent_config['capabilities'] = capabilities
            
            conn.close()
            return agent_config
            
        except Exception as e:
            console.print(f"❌ Error loading agent config: {e}", style="red")
            return None
    
    def get_all_active_agents(self) -> List[Dict[str, Any]]:
        """Get all active agents from database"""
        try:
            conn = sqlite3.connect(self.db_path)
            conn.row_factory = sqlite3.Row
            cursor = conn.cursor()
            
            cursor.execute("SELECT * FROM agents WHERE status = 'active' ORDER BY is_core DESC, name ASC")
            rows = cursor.fetchall()
            
            agents = []
            for row in rows:
                agent_config = dict(row)
                # Get capabilities
                cursor.execute("SELECT capability FROM agent_capabilities WHERE agent_id = ?", (agent_config['id'],))
                capabilities = [cap[0] for cap in cursor.fetchall()]
                agent_config['capabilities'] = capabilities
                agents.append(agent_config)
            
            conn.close()
            return agents
            
        except Exception as e:
            console.print(f"❌ Error loading agents: {e}", style="red")
            return []
    
    def create_agent_from_config(self, config: Dict[str, Any], router: Any = None, **kwargs) -> Agent:
        """Create CrewAI Agent from database configuration"""
        try:
            # Get LLM based on config
            llm = self._get_llm_for_agent(config, router)
            
            # Create agent with all parameters from database
            agent = Agent(
                role=config['role'],
                goal=config['goal'],
                backstory=config['backstory'],
                llm=llm,
                verbose=bool(config.get('verbose', 1)),
                allow_delegation=bool(config.get('allow_delegation', 0)),
                max_iter=config.get('max_iter', 25),
                max_rpm=config.get('max_rpm'),
                max_execution_time=config.get('max_execution_time'),
                allow_code_execution=bool(config.get('allow_code_execution', 0)),
                max_retry_limit=config.get('max_retry_limit', 2),
                system_template=config.get('system_template'),
                prompt_template=config.get('prompt_template'),
                response_template=config.get('response_template'),
                **kwargs
            )
            
            console.print(f"✅ Created dynamic agent: {config['role']}", style="green")
            return agent
            
        except Exception as e:
            console.print(f"❌ Error creating agent {config['role']}: {e}", style="red")
            raise
    
    def _get_llm_for_agent(self, config: Dict[str, Any], router: Any = None) -> Any:
        """Get appropriate LLM for agent based on config"""
        llm_model = config.get('llm_model', 'local')
        
        if llm_model == 'claude' and os.getenv('ANTHROPIC_API_KEY'):
            return LLM(model='anthropic/claude-sonnet-4-20250514')
        elif llm_model == 'gpt' and os.getenv('OPENAI_API_KEY'):
            return LLM(model='openai/gpt-4')
        elif router:
            return router.get_llm_for_role("general")
        else:
            # Default to local
            from src.config import config as app_config
            from langchain_ollama import OllamaLLM
            return OllamaLLM(model=app_config.model.name, base_url=app_config.model.base_url)
    
    def create_agent_by_role(self, role: str, router: Any = None, **kwargs) -> Optional[Agent]:
        """Create agent by role name"""
        config = self.get_agent_config(role=role)
        if config:
            return self.create_agent_from_config(config, router, **kwargs)
        return None
    
    def get_available_roles(self) -> List[str]:
        """Get list of available agent roles"""
        agents = self.get_all_active_agents()
        return [agent['role'] for agent in agents]

# Global instance
dynamic_loader = DynamicAgentLoader()

def create_dynamic_agent(role: str, router: Any = None, **kwargs) -> Optional[Agent]:
    """Convenience function to create agent by role"""
    return dynamic_loader.create_agent_by_role(role, router, **kwargs)

def get_available_agent_roles() -> List[str]:
    """Get all available agent roles"""
    return dynamic_loader.get_available_roles()