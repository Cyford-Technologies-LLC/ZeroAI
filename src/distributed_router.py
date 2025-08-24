"""
Distributed AI Router - Routes tasks to best available peer
"""

from typing import Optional
from peer_discovery import peer_discovery, PeerNode
import requests
from rich.console import Console

console = Console()

class DistributedRouter:
    """Routes AI tasks to the best available peer"""
    
    def __init__(self):
        self.peer_discovery = peer_discovery
        # Start peer discovery in background
        self.peer_discovery.start_discovery_service()
    
    def get_optimal_endpoint(self, task: str = "", model: str = "llama3.2:1b") -> tuple[str, str]:
        """Get optimal endpoint for AI task"""
        
        # Estimate memory requirements based on model
        memory_requirements = {
            "llama3.2:1b": 2.0,
            "llama3.1:8b": 8.0,
            "codellama:13b": 13.0,
            "llama3.1:70b": 40.0
        }
        
        min_memory = memory_requirements.get(model, 4.0)
        
        # Find best peer
        best_peer = self.peer_discovery.get_best_peer(model=model, min_memory=min_memory)
        
        if best_peer:
            console.print(f"🌐 Using peer: {best_peer.name} ({best_peer.ip})", style="green")
            console.print(f"   Memory: {best_peer.capabilities.memory_gb:.1f}GB, Load: {best_peer.capabilities.load_avg:.1f}%", style="dim")
            return f"http://{best_peer.ip}:11434", best_peer.name
        else:
            console.print("💻 Using local processing (no suitable peers)", style="blue")
            return "http://localhost:11434", "local"
    
    def add_peer(self, ip: str, port: int = 8080, name: str = None) -> bool:
        """Add a new peer to the network"""
        success = self.peer_discovery.add_peer(ip, port, name)
        if success:
            console.print(f"✅ Added peer: {name or ip}", style="green")
        else:
            console.print(f"❌ Failed to add peer: {ip}", style="red")
        return success
    
    def list_peers(self):
        """List all known peers and their status"""
        console.print("\n🌐 Known Peers:")
        console.print("-" * 80)
        
        for peer in self.peer_discovery.peers.values():
            status = "🟢 Online" if peer.capabilities.available else "🔴 Offline"
            console.print(f"{status} {peer.name} ({peer.ip})")
            console.print(f"   CPU: {peer.capabilities.cpu_cores} cores, RAM: {peer.capabilities.memory_gb:.1f}GB")
            if peer.capabilities.gpu_memory_gb > 0:
                console.print(f"   GPU: {peer.capabilities.gpu_memory_gb:.1f}GB VRAM")
            console.print(f"   Models: {', '.join(peer.capabilities.models[:3])}{'...' if len(peer.capabilities.models) > 3 else ''}")
            console.print(f"   Load: {peer.capabilities.load_avg:.1f}%")
            console.print()
    
    def process_with_peer(self, peer: PeerNode, prompt: str, model: str) -> Optional[str]:
        """Process a task with a specific peer"""
        try:
            response = requests.post(
                f"http://{peer.ip}:11434/api/generate",
                json={
                    "model": model,
                    "prompt": prompt,
                    "stream": False
                },
                timeout=60
            )
            
            if response.status_code == 200:
                return response.json().get("response")
        except Exception as e:
            console.print(f"❌ Error processing with peer {peer.name}: {e}", style="red")
        
        return None

# Global distributed router instance
distributed_router = DistributedRouter()