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

from peer_discovery import PeerDiscovery, PeerNode
from langchain_community.llms.ollama import Ollama
from crewai import LLM
from src.config import config

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

# --- Model preference lists based on agent roles ---
MODEL_PREFERENCES = {
    "developer": ["codellama:13b", "llama3.1:8b", "llama3.2:latest", "llama3.2:1b"],
    "research": ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llama3.2:1b"],
    "documentation": ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"],
    "devops_orchestrator": ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"],
    "repo_manager": ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"],
    "general": ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llava:7b", "llama3.2:1b"],
    "customer_service": ["llama3.2:latest", "gemma2:2b", "llama3.2:1b"],
    "tech_support": ["llama3.2:latest", "gemma2:2b", "llama3.2:1b"],
    "default": ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llava:7b", "llama3.2:1b"]
}

KEYWORDS_TO_CATEGORY = {
    "coding": "developer",
    "php": "developer",
    "python": "developer",
    "javascript": "developer",
    "html": "developer",
    "css": "developer",
    "sql": "developer",
    "fix": "developer",
    "bug": "developer",
    "issue": "developer",
    "research": "research",
    "analyze": "research",
    "documentation": "documentation",
    "write": "documentation",
    "orchestrator": "devops_orchestrator",
    "maintenance": "general",
    "health": "general",
    "project health": "general",
    "dependencies": "general",
    "test suites": "general",
    "support": "tech_support",
    "customer": "customer_service",
    "greeting": "customer_service"
}

peer_discovery_instance = PeerDiscovery()


class DistributedRouter:
    """Manages routing logic based on network state and model requirements."""

    def __init__(self, peer_discovery_instance):
        self.peer_discovery = peer_discovery_instance
        self.peer_discovery.start_discovery_service()

    def _get_local_ollama_models(self) -> List[str]:
        try:
            with open("pulled_models.json", "r") as f:
                return json.load(f)
        except (FileNotFoundError, json.JSONDecodeError):
            console.print("⚠️ pulled_models.json not found or is invalid. Assuming no local models.", style="yellow")
            return []

    def get_local_llm(self, model_name: str, base_url: str = None) -> Optional[LLM]:
        if model_name in self._get_local_ollama_models():
            if base_url is None:
                base_url = os.getenv("OLLAMA_HOST", "http://ollama:11434")
            console.print(f"🔗 Using local LLM for '{model_name}' at [bold green]{base_url}[/bold green]",
                          style="blue")
            llm = LLM(
                model=f"ollama/{model_name}",
                base_url=base_url,
                temperature=config.model.temperature
            )
            return llm
        return None

    def get_optimal_endpoint_and_model(self, prompt: str, failed_peers: Optional[List[str]] = None,
                                       model_preference_list: Optional[List[str]] = None) -> Tuple[
        Optional[str], Optional[str], Optional[str]]:
        if failed_peers is None:
            failed_peers = []

        if model_preference_list is None:
            prompt_lower = prompt.lower()
            category = next((cat for key, cat in KEYWORDS_TO_CATEGORY.items() if key in prompt_lower), "default")
            model_preference_list = MODEL_PREFERENCES.get(category, MODEL_PREFERENCES["default"])

        all_peers = self.peer_discovery.get_peers()
        all_candidates = []
        local_ollama_models = self._get_local_ollama_models()

        console.print(f"🔎 Analyzing peers for task with model preference: {model_preference_list}", style="blue")

        for peer in all_peers:
            if peer.name in failed_peers:
                console.print(f"   🚫 Skipping failed peer: {peer.name}", style="yellow")
                continue

            available_models = local_ollama_models if peer.name == "local-node" else peer.capabilities.models
            console.print(f"   Peer [bold cyan]{peer.name}[/bold cyan] reports available models: {available_models}",
                          style="dim")

            if not available_models:
                console.print(f"      - 🚫 Skipping peer {peer.name}: No models reported as available.", style="red")
                continue

            for model in model_preference_list:
                if model in available_models:
                    required_memory = MODEL_MEMORY_MAP.get(model)
                    if required_memory is None:
                        console.print(f"      - ⚠️ Skipping model {model}: memory requirements unknown.",
                                      style="yellow")
                        continue

                    peer_memory = peer.capabilities.gpu_memory if peer.capabilities.gpu_available else peer.capabilities.memory
                    if required_memory <= peer_memory:
                        all_candidates.append({
                            "peer": peer,
                            "model": model
                        })
                        console.print(
                            f"      - ✅ Candidate found: Model=[bold yellow]{model}[/bold yellow] on Peer=[bold cyan]{peer.name}[/bold cyan]",
                            style="green")
                    else:
                        console.print(
                            f"      - 🚫 Skipping model {model} on peer {peer.name}: insufficient memory ({required_memory} GiB required, {peer_memory} GiB available).",
                            style="red")
                else:
                    console.print(f"      - 🚫 Model {model} not available on peer {peer.name}.", style="red")

        def get_score(candidate):
            peer = candidate['peer']
            gpu_score = 1000 if peer.capabilities.gpu_available else 0
            memory_score = peer.capabilities.gpu_memory * 10 + peer.capabilities.memory
            load_score = max(0, 100 - peer.capabilities.load_avg)
            model_index_score = len(model_preference_list) - model_preference_list.index(candidate['model'])
            return (gpu_score + memory_score + load_score + model_index_score)

        all_candidates.sort(key=get_score, reverse=True)

        if all_candidates:
            best_candidate = all_candidates[0]
            peer = best_candidate['peer']
            model = best_candidate['model']
            console.print(
                f"✅ Optimal Endpoint Selected: Peer=[bold cyan]{peer.name}[/bold cyan], Model=[bold yellow]{model}[/bold yellow]",
                style="green")
            return f"http://{peer.ip}:11434", peer.name, model

        console.print("❌ No suitable peer/model combination found. Routing failed.", style="red")
        raise RuntimeError("No suitable peer or model found. All attempts failed.")

    def get_llm_for_task(self, prompt: str) -> Optional[LLM]:
        base_url, peer_name, model_name = self.get_optimal_endpoint_and_model(prompt)
        if base_url:
            llm = LLM(
                model=f"ollama/{model_name}",
                base_url=base_url,
                temperature=config.model.temperature
            )
            return llm
        return None

    def get_llm_for_role(self, role: str) -> Optional[LLM]:
        prompt = f"LLM selection for a {role} role."
        base_url, peer_name, model_name = self.get_optimal_endpoint_and_model(prompt)
        if base_url:
            llm = LLM(
                model=f"ollama/{model_name}",
                base_url=base_url,
                temperature=config.model.temperature
            )
            return llm
        return None


@lru_cache()
def get_distributed_router_dependency() -> DistributedRouter:
    """FastAPI dependency to provide a cached instance of the DistributedRouter."""
    return DistributedRouter(peer_discovery_instance)
