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
        Directly queries the local Ollama instance to get available models.
        This provides a more reliable source of truth than the discovery cache.
        """
        try:
            ollama_url = "http://host.docker.internal:11434"
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
        local_node = self.peer_discovery.get_local_node()
        all_peers_with_local = all_peers + [local_node]

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
                return "http://host.docker.internal:11434", "local", preferred_model

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

        # 3. Final fallback if no suitable model is found anywhere
        console.print("⚠️  No suitable peer or model found in preferences. Falling back to local 'llama3.2:1b'.", style="red")

        if "llama3.2:1b" in local_ollama_models:
            console.print("✅ Local fallback model 'llama3.2:1b' found.", style="green")
            return "http://host.docker.internal:11434", "local", "llama3.2:1b"
        else:
            console.print("❌ Local fallback model 'llama3.2:1b' not found after direct check.", style="red")
            raise RuntimeError("Local fallback model 'llama3.2:1b' not found. Ollama may be misconfigured or discovery failed.")

distributed_router = DistributedRouter(peer_discovery)
