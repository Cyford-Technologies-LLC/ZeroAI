# distributed_router.py

import sys
from pathlib import Path
from typing import Optional, List, Tuple, Dict, Any
from rich.console import Console
import requests
import time
import litellm
import os

from peer_discovery import peer_discovery, PeerNode

console = Console()

# Mapping model names to their approximate system memory requirements in GB
MODEL_MEMORY_MAP = {
    "llama3.1:8b": 5.6,
    "llama3.2:latest": 3.0,
    "llama3.2:1b": 2.3,
    "codellama:13b": 8.0,
    "codellama:7b": 5.0,
    "gemma2:2b": 3.5,
    "llava:7b": 5.0,
}

# Function to get the memory limit from the cgroup file
def get_container_memory_limit_gb() -> float:
    try:
        with open("/sys/fs/cgroup/memory/memory.limit_in_bytes", "r") as f:
            limit_in_bytes = int(f.read())
            # A very large number indicates no memory limit, so return a reasonable max.
            if limit_in_bytes > (1024**4):
                return float('inf')
            return limit_in_bytes / (1024**3)
    except (FileNotFoundError, ValueError):
        return float('inf') # Return infinity if not in a cgroup or file not found


class DistributedRouter:
    def __init__(self, peer_discovery_instance):
        self.peer_discovery = peer_discovery_instance
        self.peer_discovery.start_discovery_service()

    def _get_local_ollama_models(self) -> List[str]:
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

        model_preference = ["codellama:13b", "llama3.1:8b", "llama3.2:latest", "llama3.2:1b", "codellama:7b", "gemma2:2b", "llava:7b"] if is_coding_task else ["llama3.1:8b", "llama3.2:latest", "llama3.2:1b", "gemma2:2b", "llava:7b"]

        endpoints_to_try: List[Dict[str, Any]] = []

        container_memory_limit = get_container_memory_limit_gb()
        console.print(f"System has a memory limit of [bold green]{container_memory_limit:.1f} GiB[/bold green].")

        local_ollama_models = self._get_local_ollama_models()

        # Build list of all potential endpoints
        for model in model_preference:
            # Check local models first, and ensure they meet memory requirements
            if "local" not in failed_peers and model in local_ollama_models:
                required_memory = MODEL_MEMORY_MAP.get(model, float('inf'))
                if required_memory < container_memory_limit:
                    endpoints_to_try.append({"model": model, "endpoint": "http://ollama:11434", "peer_name": "local"})

            # Consider remote peers (no memory check needed here, assuming peers have resources)
            eligible_peers = [
                peer for peer in all_peers
                if peer.capabilities.available and model in peer.capabilities.models and peer.name not in failed_peers
            ]
            if eligible_peers:
                eligible_peers.sort(key=lambda p: p.capabilities.load_avg)
                for peer in eligible_peers:
                    endpoints_to_try.append({"model": model, "endpoint": f"http://{peer.ip}:11434", "peer_name": peer.name})

        # Return the next viable option
        for endpoint_info in endpoints_to_try:
            return endpoint_info['endpoint'], endpoint_info['peer_name'], endpoint_info['model']

        raise RuntimeError("No suitable model found locally that meets memory requirements, or on discovered peers. All attempts failed.")
