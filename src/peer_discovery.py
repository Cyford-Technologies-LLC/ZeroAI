# /opt/ZeroAI/src/peer_discovery.py

import sys
import os
import threading
import time
import requests
import yaml
from pathlib import Path
from typing import Dict, Any, Optional, List, Tuple
from rich.console import Console

console = Console()

# Define the path for the peers configuration file
PEERS_FILE = Path(__file__).parent / "peers.yml"

class PeerCapabilities:
    def __init__(self, **kwargs):
        self.available: bool = kwargs.get("available", False)
        self.load_avg: float = kwargs.get("load_avg", 0.0)
        self.models: List[str] = kwargs.get("models", [])
        self.last_seen: float = kwargs.get("last_seen", 0.0)

class PeerNode:
    def __init__(self, name: str, ip: str, port: int, capabilities: Optional[PeerCapabilities] = None):
        self.name = name
        self.ip = ip
        self.port = port
        self.capabilities = capabilities or PeerCapabilities()

class PeerDiscovery:
    def __init__(self, ollama_service_name: str = "ollama"):
        self.peers: Dict[str, PeerNode] = {}
        self.ollama_service_name = ollama_service_name
        self._load_peers_from_file()
        self.local_node = self._get_local_node()

    def _load_peers_from_file(self):
        """Loads peers from the peers.yml file."""
        if PEERS_FILE.exists():
            try:
                with open(PEERS_FILE, 'r') as f:
                    peers_data = yaml.safe_load(f) or {}
                    for name, data in peers_data.items():
                        self.peers[name] = PeerNode(
                            name=name,
                            ip=data['ip'],
                            port=data.get('port', 8080)
                        )
                console.print("✅ Peers loaded from peers.yml.", style="green")
            except Exception as e:
                console.print(f"❌ Failed to load peers from peers.yml: {e}", style="red")
                self.peers = {}

    def _save_peers_to_file(self):
        """Saves current peers to the peers.yml file."""
        peers_data = {
            peer.name: {"ip": peer.ip, "port": peer.port}
            for peer in self.peers.values()
        }
        try:
            with open(PEERS_FILE, 'w') as f:
                yaml.safe_dump(peers_data, f, sort_keys=False)
            console.print("✅ Peers saved to peers.yml.", style="green")
        except Exception as e:
            console.print(f"❌ Failed to save peers to peers.yml: {e}", style="red")

    def _get_local_node(self) -> PeerNode:
        """Dynamically creates the local node reference."""
        # Fix: Ensure the local node uses the 'ollama' service name for internal traffic
        return PeerNode(name="local-node", ip=self.ollama_service_name, port=11434)

    def _discover_single_peer(self, node: PeerNode):
        """Checks a single peer and updates its capabilities."""
        try:
            ollama_url = f"http://{node.ip}:11434"
            response = requests.get(f"{ollama_url}/api/tags", timeout=5)
            response.raise_for_status()
            models = [m['name'] for m in response.json().get('models', [])]
            node.capabilities.available = True
            node.capabilities.models = models
            node.capabilities.last_seen = time.time()
            node.capabilities.load_avg = 0.0
            console.print(f"✅ Discovered peer {node.name} at {node.ip} with models: {models}", style="dim")
        except requests.exceptions.RequestException:
            node.capabilities.available = False
            console.print(f"❌ Failed to connect to peer {node.name} at {node.ip}", style="dim")

    def _discover_peers(self):
        """
        Periodically checks known peers to update their capabilities.
        This runs in a background thread.
        """
        while True:
            # Fix: Use a copy of the dictionary to avoid issues during modification
            all_nodes = list(self.peers.values()) + [self.local_node]
            for node in all_nodes:
                self._discover_single_peer(node)
            time.sleep(60)

    def start_discovery_service(self):
        """Starts the discovery thread."""
        discovery_thread = threading.Thread(target=self._discover_peers, daemon=True)
        discovery_thread.start()

    def add_peer(self, ip: str, port: int, name: str) -> Tuple[bool, str]:
        """Adds a new peer and saves it to the configuration file."""
        if not name:
            name = f"{ip}:{port}"

        if name in self.peers:
            return False, f"Peer with name '{name}' already exists."
        if any(p.ip == ip for p in self.peers.values()):
            return False, f"Peer with IP '{ip}' already exists."

        try:
            ollama_url = f"http://{ip}:11434"
            response = requests.get(f"{ollama_url}/api/tags", timeout=5)
            response.raise_for_status()

            new_peer = PeerNode(name=name, ip=ip, port=port)
            self.peers[name] = new_peer
            self._save_peers_to_file()
            return True, "Peer added successfully!"
        except requests.exceptions.RequestException as e:
            return False, f"Failed to connect to new peer at {ip}:11434: {e}"

    def list_peers(self):
        """Displays a list of all discovered and configured peers."""
        console.print("\n📋 [bold]Peer List[/bold]")
        if not self.peers:
            console.print("   No external peers configured.", style="dim")
        else:
            for peer in self.peers.values():
                status = "[green]Available[/green]" if peer.capabilities.available else "[red]Offline[/red]"
                console.print(f"   - [bold]{peer.name}[/bold] ({peer.ip}:{peer.port}) - Status: {status}")
                if peer.capabilities.available:
                    models = ", ".join(peer.capabilities.models)
                    console.print(f"     Models: {models}", style="dim")
                    console.print(f"     Load: {peer.capabilities.load_avg:.1f}%", style="dim")

        local_status = "[green]Available[/green]" if self.local_node.capabilities.available else "[red]Offline[/red]"
        console.print("\n📋 [bold]Local Node[/bold]")
        console.print(f"   - [bold]{self.local_node.name}[/bold] ({self.local_node.ip}:{self.local_node.port}) - Status: {local_status}")
        if self.local_node.capabilities.available:
            models = ", ".join(self.local_node.capabilities.models)
            console.print(f"     Models: {models}", style="dim")

    def get_peers(self) -> List[PeerNode]:
        return list(self.peers.values())

    def get_local_node(self) -> PeerNode:
        return self.local_node

# Create a singleton instance
peer_discovery = PeerDiscovery()
