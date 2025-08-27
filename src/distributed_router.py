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

        # 1. GPU priority (1000 points per available GPU)
        if peer.capabilities.gpu_available:
            # Score based on GPU memory headroom and load
            if peer.capabilities.gpu_memory > 0:
                score += 1000.0 * (1.0 - (peer.capabilities.gpu_load or 0.0)) * (peer.capabilities.gpu_memory / 16) # Scale based on 16GB GPU memory
            else:
                score += 1000.0 * (1.0 - (peer.capabilities.gpu_load or 0.0))

        # 2. Memory priority (100 points per GiB of free memory)
        memory_headroom = peer.capabilities.memory - model_memory_req
        if memory_headroom > 0:
            score += 100.0 * memory_headroom
        else:
            return -1.0 # Not enough memory

        # 3. CPU priority (10 points per 10% of free CPU)
        score += 10.0 * (1.0 - (peer.capabilities.load_avg or 0.0))

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

        endpoints_with_scores: List[Dict[str, Any]] = []

        for peer in all_peers:
            if peer.name in failed_peers:
                continue

            for model in model_preference:
                if model in peer.capabilities.models:
                    required_memory = MODEL_MEMORY_MAP.get(model, float('inf'))
                    score = self._calculate_score(peer, required_memory)
                    if score > 0:
                        endpoints_with_scores.append({
                            "model": model,
                            "endpoint": f"http://{peer.ip}:11434",
                            "peer_name": peer.name,
                            "score": score,
                            "gpu_available": peer.capabilities.gpu_available
                        })

        # Sort by GPU availability first, then by score
        endpoints_with_scores.sort(key=lambda x: (x['gpu_available'], x['score']), reverse=True)

        if endpoints_with_scores:
            best_option = endpoints_with_scores[0]
            return best_option['endpoint'], best_option['peer_name'], best_option['model']

        # If no suitable endpoint is found, try the final fallback
        local_peer_info = next((peer for peer in all_peers if peer.name == "local-node"), None)
        if local_peer_info and local_peer_info.name not in failed_peers:
            model = "llama3.2:1b"
            if model in local_peer_info.capabilities.models:
                required_memory = MODEL_MEMORY_MAP.get(model, float('inf'))
                if required_memory <= local_peer_info.capabilities.memory:
                    return f"http://{local_peer_info.ip}:11434", local_peer_info.name, model

        raise RuntimeError("No suitable model found that meets memory requirements locally or on discovered peers. All attempts failed.")
