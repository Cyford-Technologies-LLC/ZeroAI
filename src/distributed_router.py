# distributed_router.py

import sys
from pathlib import Path
from typing import Optional, List, Tuple, Dict, Any
from rich.console import Console
import requests
import time
import litellm
import os
import json

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

        # PRIORITIZE SMALLER MODELS FOR LOCAL PEER TO AVOID MEMORY ERRORS
        model_preference = [
            "llama3.2:1b", "gemma2:2b", "llama3.2:latest",
            "codellama:7b", "llama3.1:8b", "codellama:13b", "llava:7b"
        ] if is_coding_task else [
            "llama3.2:1b", "llama3.2:latest", "gemma2:2b",
            "llama3.1:8b", "llava:7b"
        ]

        endpoints_to_try: List[Dict[str, Any]] = []

        local_ollama_models = self._get_local_ollama_models()

        # Tier 1: Eligible Local Models (already filtered for memory by the pre-pull script)
        for model in model_preference:
            if model in local_ollama_models and "local-node" not in failed_peers:
                local_peer_info = next((peer for peer in all_peers if peer.name == "local-node"), None)
                if local_peer_info:
                    endpoints_to_try.append({"model": model, "endpoint": f"http://{local_peer_info.ip}:11434", "peer_name": local_peer_info.name})

        # Tier 2: Remote Peers (filtered by memory)
        for model in model_preference:
            eligible_peers = [
                peer for peer in all_peers
                if peer.capabilities.available and model in peer.capabilities.models and peer.name not in failed_peers and peer.name != "local-node"
            ]
            if eligible_peers:
                eligible_peers.sort(key=lambda p: p.capabilities.load_avg)
                for peer in eligible_peers:
                    required_memory = MODEL_MEMORY_MAP.get(model, float('inf'))
                    if required_memory <= peer.capabilities.memory:
                        endpoints_to_try.append({"model": model, "endpoint": f"http://{peer.ip}:11434", "peer_name": peer.name})

        # Return the next viable option
        if endpoints_to_try:
            best_option = endpoints_to_try
            return best_option['endpoint'], best_option['peer_name'], best_option['model']

        raise RuntimeError("No suitable model found that meets memory requirements locally or on discovered peers. All attempts failed.")
