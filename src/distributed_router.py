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

class DistributedRouter:
    def __init__(self, peer_discovery_instance):
        self.peer_discovery = peer_discovery_instance
        self.peer_discovery.start_discovery_service()

    def _get_local_ollama_models(self) -> List[str]:
        try:
            ollama_url = os.environ.get("OLLAMA_HOST", "http://ollama:11434")
            response = requests.get(f"{ollama_url}/api/tags", timeout=5)
            response.raise_for_status()
            models = [m['name'] for m in response.json().get('models', [])]
            return models
        except requests.exceptions.RequestException:
            return []

    def _calculate_score(self, peer: PeerNode, model_memory_req: float) -> float:
        """Calculate a score for a peer based on GPU, Memory, and CPU."""
        if not peer.capabilities.available:
            return -1.0 # Invalid peer

        score = 0.0

        # 1. GPU priority
        if peer.capabilities.gpu_available:
            score += 1000.0 * (1.0 - peer.capabilities.gpu_load)

        # 2. Memory priority
        memory_headroom = peer.capabilities.memory - model_memory_req
        if memory_headroom > 0:
            score += 100.0 * (memory_headroom / peer.capabilities.memory)
        else:
            return -1.0 # Not enough memory

        # 3. CPU priority
        score += 10.0 * (1.0 - peer.capabilities.load_avg)

        return score

    def get_optimal_endpoint_and_model(self, prompt: str, failed_peers: Optional[List[str]] = None) -> Tuple[str, str, str]:
        if failed_peers is None:
            failed_peers = []

        all_peers = self.peer_discovery.get_peers()
        is_coding_task = any(
            keyword in prompt.lower() for keyword in ['code', 'php', 'python', 'javascript', 'html', 'css', 'sql']
        )

        model_preference = [
            "codellama:13b", "llama3.1:8b", "llama3.2:latest",
            "llama3.2:1b", "codellama:7b", "gemma2:2b", "llava:7b"
        ] if is_coding_task else [
            "llama3.1:8b", "llama3.2:latest", "llama3.2:1b",
            "gemma2:2b", "llava:7b"
        ]

        endpoints_to_try: List[Dict[str, Any]] = []

        # 1. Prioritize GPU peers
        for model in model_preference:
            for peer in all_peers:
                if peer.capabilities.gpu_available and model in peer.capabilities.models and peer.name not in failed_peers:
                    required_memory = MODEL_MEMORY_MAP.get(model, float('inf'))
                    if required_memory <= peer.capabilities.memory:
                        endpoints_to_try.append({"model": model, "endpoint": f"http://{peer.ip}:11434", "peer_name": peer.name, "score": self._calculate_score(peer, required_memory)})

        # 2. Prioritize other remote peers
        for model in model_preference:
            for peer in all_peers:
                if not peer.capabilities.gpu_available and model in peer.capabilities.models and peer.name not in failed_peers and peer.name != "local-node":
                    required_memory = MODEL_MEMORY_MAP.get(model, float('inf'))
                    if required_memory <= peer.capabilities.memory:
                        endpoints_to_try.append({"model": model, "endpoint": f"http://{peer.ip}:11434", "peer_name": peer.name, "score": self._calculate_score(peer, required_memory)})

        # 3. Fallback to local
        local_peer_info = next((peer for peer in all_peers if peer.name == "local-node"), None)
        if local_peer_info and local_peer_info.name not in failed_peers:
            for model in model_preference:
                if model in local_peer_info.capabilities.models:
                    required_memory = MODEL_MEMORY_MAP.get(model, float('inf'))
                    if required_memory <= local_peer_info.capabilities.memory:
                         endpoints_to_try.append({"model": model, "endpoint": f"http://{local_peer_info.ip}:11434", "peer_name": local_peer_info.name, "score": self._calculate_score(local_peer_info, required_memory)})

        # 4. Fallback to llama3.2:1b if all else fails
        if not endpoints_to_try and local_peer_info and local_peer_info.name not in failed_peers:
             model = "llama3.2:1b"
             if model in local_peer_info.capabilities.models:
                 required_memory = MODEL_MEMORY_MAP.get(model, float('inf'))
                 if required_memory <= local_peer_info.capabilities.memory:
                     endpoints_to_try.append({"model": model, "endpoint": f"http://{local_peer_info.ip}:11434", "peer_name": local_peer_info.name, "score": 99.0})

        # Sort by score and return the best option
        if endpoints_to_try:
            endpoints_to_try.sort(key=lambda x: x['score'], reverse=True)
            best_option = endpoints_to_try[0]
            return best_option['endpoint'], best_option['peer_name'], best_option['model']

        raise RuntimeError("No suitable model found that meets memory requirements locally or on discovered peers. All attempts failed.")

