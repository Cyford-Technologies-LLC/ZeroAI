# /opt/ZeroAI/src/distributed_router.py

import sys
from pathlib import Path
from typing import Optional, List, Tuple, Dict, Any
from rich.console import Console
import requests
import time
import litellm
import os
import json
import warnings
from functools import lru_cache

from peer_discovery import peer_discovery, PeerNode
from langchain_community.llms.ollama import Ollama

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

# --- Shared instance of PeerDiscovery for the entire application lifecycle ---
# This remains a long-lived instance to manage network state, but is not the router itself.
peer_discovery_instance = peer_discovery.PeerDiscovery()

class DistributedRouter:
    """Manages routing logic based on network state and model requirements."""
    def __init__(self, peer_discovery_instance):
        self.peer_discovery = peer_discovery_instance
        self.peer_discovery.start_discovery_service()

    def _get_local_ollama_models(self) -> List[str]:
        """
        Reads the pre-pulled models from the generated JSON file.
        This list is already filtered for local memory requirements.
        """
        try:
            with open("pulled_models.json", "r") as f:
                return json.load(f)
        except (FileNotFoundError, json.JSONDecodeError):
            console.print("⚠️ pulled_models.json not found or is invalid. Assuming no local models.", style="yellow")
            return []

    def get_optimal_endpoint_and_model(self, prompt: str, failed_peers: Optional[List[str]] = None) -> Tuple[str, str, str]:
        if failed_peers is None:
            failed_peers = []

        all_peers = self.peer_discovery.get_peers()
        is_coding_task = any(
            keyword in prompt.lower() for keyword in ['code', 'php', 'python', 'javascript', 'html', 'css', 'sql']
        )

        model_preference = [
            "codellama:13b", "llama3.1:8b", "codellama:7b", "gemma2:2b",
            "llama3.2:latest", "llava:7b", "llama3.2:1b"
        ] if is_coding_task else [
            "llama3.1:8b", "llama3.2:latest", "gemma2:2b",
            "llava:7b", "llama3.2:1b"
        ]

        all_candidates = []
        local_ollama_models = self._get_local_ollama_models()

        for peer in all_peers:
            if peer.name in failed_peers:
                continue

            available_models = local_ollama_models if peer.name == "local-node" else peer.capabilities.models

            for model in model_preference:
                if model in available_models:
                    required_memory = MODEL_MEMORY_MAP.get(model)
                    if required_memory is None:
                        continue

                    if required_memory <= peer.capabilities.memory:
                        all_candidates.append({
                            "peer": peer,
                            "model": model
                        })

        # --- REVISED SORTING LOGIC ---
        # Sort all candidates based on the specified priority using a more granular scoring system.
        # Higher score is better.
        def get_score(candidate):
            peer = candidate['peer']

            # Heavy weight for GPU, prioritizing peers with a GPU first.
            gpu_score = 1000 if peer.capabilities.gpu_available else 0

            # Prioritize higher GPU and system memory.
            memory_score = peer.capabilities.gpu_memory * 10 + peer.capabilities.memory

            # Strong penalty for high load average. Invert load_avg for sorting.
            # A load_avg of 0 gets a high score, a high load_avg gets a low score.
            load_score = max(0, 100 - peer.capabilities.load_avg)

            # Prioritize better models as a tie-breaker.
            model_index_score = len(model_preference) - model_preference.index(candidate['model'])

            return (gpu_score + memory_score + load_score + model_index_score)

        all_candidates.sort(key=get_score, reverse=True)

        # Return the best candidate if found
        if all_candidates:
            best_candidate = all_candidates[0]
            peer = best_candidate['peer']
            model = best_candidate['model']
            console.print(f"Optimal Endpoint Selected: Peer=[bold cyan]{peer.name}[/bold cyan], Model=[bold yellow]{model}[/bold yellow]", style="green")
            return f"http://{peer.ip}:11434", peer.name, model

        # If no candidates meet requirements, use a final fallback
        console.print("❌ No suitable peer/model combination found. Falling back to smallest local model.", style="red")
        local_peer_info = next((peer for peer in all_peers if peer.name == "local-node"), None)
        if local_peer_info:
            fallback_model = "llama3.2:1b"
            return f"http://{local_peer_info.ip}:11434", "local-node", fallback_model

        raise RuntimeError("No suitable peer or model found. All attempts failed.")

# --- Use @lru_cache for dependency to reuse the same instance ---
@lru_cache()
def get_distributed_router_dependency() -> DistributedRouter:
    """FastAPI dependency to provide a cached instance of the DistributedRouter."""
    return DistributedRouter(peer_discovery_instance)
