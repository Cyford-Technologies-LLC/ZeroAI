# /opt/ZeroAI/src/distributed_router.py

import sys
from pathlib import Path
from typing import Optional, List, Tuple
from rich.console import Console

# Assuming PeerNode and other types are defined in peer_discovery
from peer_discovery import peer_discovery, PeerNode

console = Console()

class DistributedRouter:
    def __init__(self, peer_discovery_instance):
        self.peer_discovery = peer_discovery_instance
        self.peer_discovery.start_discovery_service()

    def get_optimal_endpoint_and_model(self, prompt: str) -> Tuple[str, str, str]:
        all_peers = self.peer_discovery.get_peers()
        local_node = self.peer_discovery.get_local_node()

        # Fix: Ensure local node is handled explicitly
        all_peers_with_local = all_peers + [local_node]

        is_coding_task = any(
            keyword in prompt.lower() for keyword in ['code', 'php', 'python', 'javascript', 'html', 'css', 'sql']
        )

        if is_coding_task:
            model_preference = ["codellama:13b", "llama3.1:8b", "llama3.2:1b"]
        else:
            model_preference = ["llama3.1:8b", "llama3.2:1b"]

        for preferred_model in model_preference:
            eligible_peers = [
                peer for peer in all_peers_with_local
                if peer.capabilities.available and preferred_model in peer.capabilities.models
            ]

            if eligible_peers:
                       eligible_peers.sort(key=lambda p: p.capabilities.load_avg)
                       optimal_peer = eligible_peers[0]

                       if optimal_peer.name == "local-node":
                           return "http://host.docker.internal:11434", "local", preferred_model

                       peer_ollama_url = f"http://{optimal_peer.ip}:11434"

                       console.print(
                           f"✅ Found optimal peer: [bold green]{optimal_peer.name}[/bold green] "
                           f"at {peer_ollama_url} with load {optimal_peer.capabilities.load_avg:.1f}%",
                           style="cyan"
                       )
                       return peer_ollama_url, optimal_peer.name, preferred_model

        console.print("⚠️  No suitable peer or model found. Using local fallback.", style="red")

        # Fix: Ensure local node capabilities are checked for fallback
        if "llama3.2:1b" in local_node.capabilities.models:
            return "http://host.docker.internal:11434", "local", "llama3.2:1b"
        else:
            raise Exception("Local fallback model 'llama3.2:1b' not found.")

distributed_router = DistributedRouter(peer_discovery)
