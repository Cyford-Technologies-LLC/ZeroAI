"""
Agent-to-Agent Communication System
"""

import requests
import json
from typing import Optional, Dict, Any
from rich.console import Console

console = Console()

class AgentCommunicator:
    """Handles communication between AI agents across the network"""
    
    def __init__(self):
        self.timeout = 120  # Shorter timeout for agent communication
    
    def send_task_to_peer(self, peer_ip: str, task_data: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        """Send a task to a peer agent for processing"""
        try:
            console.print(f"📤 Sending task to peer agent at {peer_ip}", style="blue")
            
            response = requests.post(
                f"http://{peer_ip}:8080/process_task",
                json=task_data,
                timeout=self.timeout
            )
            
            if response.status_code == 200:
                result = response.json()
                console.print(f"📥 Received response from peer agent", style="green")
                return result
            else:
                console.print(f"❌ Peer agent returned status {response.status_code}", style="red")
                
        except requests.exceptions.Timeout:
            console.print(f"⏰ Timeout communicating with peer agent", style="yellow")
        except Exception as e:
            console.print(f"❌ Error communicating with peer: {e}", style="red")
        
        return None
    
    def process_code_generation(self, peer_ip: str, prompt: str, model: str = "codellama:13b") -> Optional[str]:
        """Send code generation task to peer"""
        task_data = {
            "type": "code_generation",
            "prompt": prompt,
            "model": model,
            "temperature": 0.3,
            "max_tokens": 1024
        }
        
        result = self.send_task_to_peer(peer_ip, task_data)
        if result and result.get("success"):
            return result.get("response")
        
        return None
    
    def process_research_task(self, peer_ip: str, topic: str, model: str = "llama3.1:8b") -> Optional[str]:
        """Send research task to peer"""
        task_data = {
            "type": "research",
            "topic": topic,
            "model": model,
            "temperature": 0.7,
            "max_tokens": 512
        }
        
        result = self.send_task_to_peer(peer_ip, task_data)
        if result and result.get("success"):
            return result.get("response")
        
        return None

# Global agent communicator
agent_comm = AgentCommunicator()