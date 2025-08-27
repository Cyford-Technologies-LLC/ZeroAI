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

        # Define model preference list based on task type
        model_preference = [
            "codellama:13b", "llama3.1:8b", "codellama:7b", "gemma2:2b",
            "llama3.2:latest", "llava:7b", "llama3.2:1b"
        ] if is_coding_task else [
            "llama3.1:8b", "llama3.2:latest", "gemma2:2b",
            "llava:7b", "llama3.2:1b"
        ]

        # Build a comprehensive list of all valid candidates (peer + model)
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
        # Sort all candidates based on the specified priority:
        # 1. GPU (True > False)
        # 2. GPU memory (higher is better)
        # 3. System RAM (higher is better)
        # 4. **CPU load (lower is better, with a strong weighting)**
        # 5. Model preference list (as a final tie-breaker)
        all_candidates.sort(key=lambda c: (
            c['peer'].capabilities.gpu_available,
            c['peer'].capabilities.gpu_memory,
            c['peer'].capabilities.memory,
            # Invert the load_avg to prioritize lower values, giving it high priority
            -c['peer'].capabilities.load_avg,
            model_preference.index(c['model'])
        ), reverse=True)

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

