# /opt/ZeroAI/src/peer_discovery.py

import sys
import os
import threading
import time
import requests
import yaml
import psutil
from pathlib import Path
from typing import Dict, Any, Optional, List, Tuple
from rich.console import Console

console = Console()

PEERS_FILE = Path(__file__).parent / "peers.yml"

class PeerCapabilities:
    def __init__(self, **kwargs):
        self.available: bool = kwargs.get("available", False)
        self.load_avg: float = kwargs.get("load_avg", 0.0)
        self.models: List[str] = kwargs.get("models", [])
        self.last_seen: float = kwargs.get("last_seen", 0.0)
        self.available_memory: int = kwargs.get("available_memory", 0) # in bytes
        self.total_memory: int = kwargs.get("total_memory", 0) # in bytes

class PeerNode:
    def __init__(self, name: str, ip: str, port: int, capabilities: Optional[PeerCapabilities] = None):
        self.name = name
        self.ip = ip
        self.port = port
        self.capabilities = capabilities or PeerCapabilities()

class PeerDiscovery:
    def __init__(self, ollama_service_name: str = "ollama", peer_service_name: str = "zeroai_peer"):
        self.peers: Dict[str, PeerNode] = {}
        self.ollama_service_name = ollama_service_name
        self.peer_service_name = peer_service_name
        self._load_peers_from_file()
        self.local_node = self._get_local_node()

    def _load_peers_from_file(self):
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
        return PeerNode(name="local-node", ip=self.ollama_service_name, port=11434)

    def _get_local_metrics(self) -> Dict[str, Any]:
        """Gets system metrics for the local node."""
        mem = psutil.virtual_memory()
        return {
            "load_avg": psutil.cpu_percent(),
            "available_memory": mem.available,
            "total_memory": mem.total,
        }

    def _discover_single_peer(self, node: PeerNode):
        """Checks a single peer and updates its capabilities."""
        max_retries = 3
        for attempt in range(max_retries):
            try:
                # Assume Ollama is on port 11434
                ollama_url = f"http://{node.ip}:11434"
                models_response = requests.get(f"{ollama_url}/api/tags", timeout=5)
                models_response.raise_for_status()
                models = [m['name'] for m in models_response.json().get('models', [])]

                if node.name == "local-node":
                    metrics = self._get_local_metrics()
                else:
                    # Assume custom endpoint for metrics on external peers
                    # This relies on the other ZeroAI instance exposing its metrics
                    status_response = requests.get(f"http://{node.ip}:{node.port}/api/status", timeout=5)
                    status_response.raise_for_status()
                    metrics = status_response.json()

                node.capabilities.available = True
                node.capabilities.models = models
                node.capabilities.last_seen = time.time()
                node.capabilities.load_avg = metrics.get("load_avg", 0.0)
                node.capabilities.available_memory = metrics.get("available_memory", 0)
                node.capabilities.total_memory = metrics.get("total_memory", 0)
                console.print(f"✅ Discovered peer {node.name} at {node.ip} with models: {models}", style="dim")
                console.print(f"   Metrics: Load={node.capabilities.load_avg:.1f}%, Mem={node.capabilities.available_memory / (1024**3):.1f} GiB", style="dim")
                return # Exit on success
            except requests.exceptions.RequestException as e:
                console.print(f"❌ Failed to connect to peer {node.name} at {node.ip} (Attempt {attempt + 1}/{max_retries}): {e}", style="dim")
                time.sleep(1) # Wait before retrying

        node.capabilities.available = False
        console.print(f"❌ Failed to connect to peer {node.name} after {max_retries} retries.", style="red")

    def _discover_peers(self):
        while True:
            console.print("\n🔍 Initiating peer discovery cycle...", style="yellow")
            all_nodes = list(self.peers.values()) + [self.local_node]
            for node in all_nodes:
                self._discover_single_peer(node)
            console.print("🔍 Peer discovery cycle complete.", style="yellow")
            time.sleep(60)

    def start_discovery_service(self):
        discovery_thread = threading.Thread(target=self._discover_peers, daemon=True)
        discovery_thread.start()

    def add_peer(self, ip: str, port: int, name: str) -> Tuple[bool, str]:
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
                    console.print(f"     Load: {peer.capabilities.load_avg:.1f}%, Mem: {peer.capabilities.available_memory / (1024**3):.1f} GiB", style="dim")

        local_status = "[green]Available[/green]" if self.local_node.capabilities.available else "[red]Offline[/red]"
        console.print("\n📋 [bold]Local Node[/bold]")
        console.print(f"   - [bold]{self.local_node.name}[/bold] ({self.local_node.ip}:{self.local_node.port}) - Status: {local_status}")
        if self.local_node.capabilities.available:
            models = ", ".join(self.local_node.capabilities.models)
            console.print(f"     Models: {models}", style="dim")
            console.print(f"     Load: {self.local_node.capabilities.load_avg:.1f}%, Mem: {self.local_node.capabilities.available_memory / (1024**3):.1f} GiB", style="dim")

    def get_peers(self) -> List[PeerNode]:
        return list(self.peers.values())

    def get_local_node(self) -> PeerNode:
        return self.local_node

    def _check_ollama_peer(self, peer_name: str, ollama_ip: str) -> PeerCapabilities:
        for attempt in range(PEER_PING_RETRIES):
            try:
                ollama_url = f"http://{ollama_ip}:11434"

                # Use the correct API endpoint: /api/tags
                response = requests.get(f"{ollama_url}/api/tags", timeout=PEER_PING_TIMEOUT)
                response.raise_for_status()
                models = [m['name'] for m in response.json().get('models', [])]

                # Get system load and memory info
                # Note: This load/memory is for the discovery peer, not the ollama peer.
                # A full solution would require the ollama peer to expose this info.
                load = self._get_system_load()
                memory = self._get_ollama_memory(ollama_ip)

                console.print(f"✅ Discovered peer {peer_name} at {ollama_ip} with models: {models}", style="green")
                console.print(f"   Metrics: Load={load:.1f}%, Mem={memory:.1f} GiB", style="green")
                return PeerCapabilities(available=True, models=models, load_avg=load, memory=memory)
            except requests.exceptions.RequestException as e:
                console.print(f"❌ Failed to connect to peer {peer_name} at {ollama_ip} (Attempt {attempt + 1}/{PEER_PING_RETRIES}): {e}", style="red")
                time.sleep(1)

        console.print(f"❌ Failed to connect to peer {peer_name} after {PEER_PING_RETRIES} retries.", style="red")
        return PeerCapabilities(available=False)
# Create a singleton instance
peer_discovery = PeerDiscovery()
