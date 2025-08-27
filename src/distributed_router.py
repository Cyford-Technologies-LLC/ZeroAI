# /opt/ZeroAI/src/distributed_router.py

import sys
from pathlib import Path
from typing import Optional, List, Tuple
from rich.console import Console
import requests

from peer_discovery import peer_discovery, PeerNode

console = Console()

class DistributedRouter:
    def __init__(self, peer_discovery_instance):
        self.peer_discovery = peer_discovery_instance
        self.peer_discovery.start_discovery_service()

    def _get_local_ollama_models(self) -> List[str]:
        """
        Directly queries the local Ollama instance (by its service name) to get available models.
        """
        try:
            # Fix: Use the service name 'ollama' for internal Docker networking
            ollama_url = "http://ollama:11434"
            console.print(f"🔍 Pinging local Ollama at {ollama_url}...", style="yellow")
            response = requests.get(f"{ollama_url}/api/tags", timeout=5)
            response.raise_for_status()
            models = [m['name'] for m in response.json().get('models', [])]
            console.print(f"✅ Local Ollama models detected: {models}", style="green")
            return models
        except requests.exceptions.RequestException as e:
            console.print(f"❌ Failed to reach local Ollama: {e}", style="red")
            return []

    def get_optimal_endpoint_and_model(self, prompt: str) -> Tuple[str, str, str]:
        local_ollama_models = self._get_local_ollama_models()
        all_peers = self.peer_discovery.get_peers()

        is_coding_task = any(
            keyword in prompt.lower() for keyword in ['code', 'php', 'python', 'javascript', 'html', 'css', 'sql']
        )

        if is_coding_task:
            model_preference = ["codellama:13b", "llama3.1:8b", "llama3.2:1b"]
        else:
            model_preference = ["llama3.1:8b", "llama3.2:1b"]

        # 1. Prioritize local models based on direct Ollama query
        for preferred_model in model_preference:
            if preferred_model in local_ollama_models:
                console.print(f"✅ Using local model: [bold yellow]{preferred_model}[/bold yellow] via direct check.", style="green")
                # Fix: Use the service name 'ollama' for the endpoint
                return "http://ollama:11434", "local", preferred_model

        # 2. Search for optimal peer if no suitable local model found
        for preferred_model in model_preference:
            eligible_peers = [
                peer for peer in all_peers
                if peer.capabilities.available and preferred_model in peer.capabilities.models
            ]

            console.print(f"🔍 Checking for preferred model '{preferred_model}' on peers...", style="yellow")

            if eligible_peers:
                eligible_peers.sort(key=lambda p: p.capabilities.load_avg)
                optimal_peer = eligible_peers[0]

                peer_ollama_url = f"http://{optimal_peer.ip}:11434"
                console.print(
                    f"✅ Found optimal peer: [bold green]{optimal_peer.name}[/bold green] "
                    f"at {peer_ollama_url} with load {optimal_peer.capabilities.load_avg:.1f}% for model [bold yellow]{preferred_model}[/bold yellow]",
                    style="cyan"
                )
                return peer_ollama_url, optimal_peer.name, preferred_model

        # 3. Handle no suitable model found
        raise RuntimeError("No suitable model found locally or on discovered peers. Ollama may be misconfigured or discovery failed.")

distributed_router = DistributedRouter(peer_discovery)
