# /opt/ZeroAI/src/distributed_router.py

import sys
from pathlib import Path
from typing import Optional, List, Tuple
from rich.console import Console
import requests
import time
import litellm

from peer_discovery import peer_discovery, PeerNode

console = Console()

class DistributedRouter:
    def __init__(self, peer_discovery_instance):
        self.peer_discovery = peer_discovery_instance
        self.peer_discovery.start_discovery_service()

    def _get_local_ollama_models(self) -> List[str]:
        """Directly queries the local Ollama instance to get available models."""
        try:
            ollama_url = "http://ollama:11434"
            response = requests.get(f"{ollama_url}/api/tags", timeout=5)
            response.raise_for_status()
            models = [m['name'] for m in response.json().get('models', [])]
            return models
        except requests.exceptions.RequestException:
            return []

    def get_optimal_endpoint_and_model(self, prompt: str, failed_peers: Optional[List[str]] = None) -> Tuple[str, str, str]:
        if failed_peers is None:
            failed_peers = []

        all_peers = self.peer_discovery.get_peers()
        is_coding_task = any(
            keyword in prompt.lower() for keyword in ['code', 'php', 'python', 'javascript', 'html', 'css', 'sql']
        )

        model_preference = ["codellama:13b", "llama3.1:8b", "llama3.2:1b"] if is_coding_task else ["llama3.1:8b", "llama3.2:1b"]

        endpoints_to_try = []

        # New: Add remote peers first based on preference and load
        for model in model_preference:
            eligible_peers = [
                peer for peer in all_peers
                if peer.capabilities.available and model in peer.capabilities.models and peer.name not in failed_peers
            ]
            if eligible_peers:
                eligible_peers.sort(key=lambda p: p.capabilities.load_avg)
                for peer in eligible_peers:
                    endpoints_to_try.append({"model": model, "endpoint": f"http://{peer.ip}:11434", "peer_name": peer.name})

        local_ollama_models = self._get_local_ollama_models()

        # New: Add all viable local models to the list *after* remote peers
        for model in model_preference:
            if model in local_ollama_models and "local" not in failed_peers:
                endpoints_to_try.append({"model": model, "endpoint": "http://ollama:11434", "peer_name": "local"})

        # Return the next available option from the list
        for endpoint_info in endpoints_to_try:
            return endpoint_info['endpoint'], endpoint_info['peer_name'], endpoint_info['model']

        # If all else fails
        raise RuntimeError("No suitable model found locally or on discovered peers. All attempts failed.")

distributed_router = DistributedRouter(peer_discovery)
