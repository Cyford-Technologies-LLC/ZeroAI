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

from src.peer_discovery import PeerDiscovery, PeerNode
from langchain_community.llms.ollama import Ollama
from src.config import config

console = Console()

# Debug levels: 0=silent, 1=errors, 2=warnings, 3=info, 4=debug, 5=verbose
ROUTER_DEBUG_LEVEL = int(os.getenv('ROUTER_DEBUG_LEVEL', '3'))
ENABLE_ROUTER_LOGGING = os.getenv('ENABLE_ROUTER_LOGGING', 'true').lower() == 'true'

def log_router(message: str, level: int = 3, style: str = None):
    """Log router messages based on debug level"""
    if ENABLE_ROUTER_LOGGING and level <= ROUTER_DEBUG_LEVEL:
        if style:
            console.print(message, style=style)
        else:
            console.print(message)

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
    "research": ["mistral-nemo:latest", "llama3.2:latest", "gemma2:2b", "llama3.2:1b"],
    "documentation": ["mistral-nemo:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"],
    "devops_orchestrator": ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"],
    "repo_manager": ["mistral-nemo:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"],
    "general": ["mistral-nemo:latest", "llama3.2:latest", "gemma2:2b", "llava:7b", "llama3.2:1b"],
    "customer_service": ["mistral-nemo:latest", "gemma2:2b", "llama3.2:1b"],
    "tech_support": ["mistral-nemo:latest", "gemma2:2b", "llama3.2:1b"],
    "default": ["mistral-nemo:latest", "llama3.1:8b", "gemma2:2b", "llava:7b", "llama3.2:1b"]
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

peer_discovery_instance = PeerDiscovery.get_instance()


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
            log_router("⚠️ pulled_models.json not found or is invalid. Assuming no local models.", 2, "yellow")
            return []

    def get_local_llm(self, model_name: str, base_url: str = None) -> Optional[Ollama]:
        if model_name in self._get_local_ollama_models():
            if base_url is None:
                base_url = os.getenv("OLLAMA_HOST", "http://ollama:11434")
            prefixed_model_name = f"ollama/{model_name}"
            llm_config = {
                "model": prefixed_model_name,
                "base_url": base_url,
                "temperature": config.model.temperature
            }
            with warnings.catch_warnings():
                warnings.simplefilter("ignore", DeprecationWarning)
                log_router(f"🔗 Using local LLM for '{model_name}' at {base_url}", 4, "blue")
                return Ollama(**llm_config)
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
        
        log_router(f"🔎 Analyzing peers for task with model preference: {model_preference_list}", 4, "blue")

        for peer in all_peers:
            if peer.name in failed_peers:
                log_router(f"   🚫 Skipping failed peer: {peer.name}", 4, "yellow")
                continue

            available_models = local_ollama_models if peer.name == "local-node" else peer.capabilities.models
            log_router(f"   Peer {peer.name} reports available models: {available_models}", 5)

            if not available_models:
                log_router(f"      - 🚫 Skipping peer {peer.name}: No models reported as available.", 4, "red")
                continue

            for model in model_preference_list:
                if model in available_models:
                    required_memory = MODEL_MEMORY_MAP.get(model)
                    if required_memory is None:
                        log_router(f"      - ⚠️ Skipping model {model}: memory requirements unknown.", 4, "yellow")
                        continue

                    peer_memory = peer.capabilities.gpu_memory if peer.capabilities.gpu_available else peer.capabilities.memory
                    if required_memory <= peer_memory:
                        all_candidates.append({
                            "peer": peer,
                            "model": model
                        })
                        log_router(f"      - ✅ Candidate found: Model={model} on Peer={peer.name}", 5)
                    else:
                        log_router(f"      - 🚫 Skipping model {model} on peer {peer.name}: insufficient memory ({required_memory} GiB required, {peer_memory} GiB available).", 5)
                else:
                    log_router(f"      - 🚫 Model {model} not available on peer {peer.name}.", 5)

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
            model = 'mistral-nemo'  # best_candidate['model']
            log_router(f"✅ Optimal Endpoint Selected: Peer={peer.name}, Model={model}", 3, "green")
            return f"http://{peer.ip}:11434", peer.name, model

        log_router("❌ No suitable peer/model combination found. Routing failed.", 1, "red")
        raise RuntimeError("No suitable peer or model found. All attempts failed.")

    def get_llm_for_task(self, prompt: str) -> Optional[Ollama]:
        base_url, peer_name, model_name = self.get_optimal_endpoint_and_model(prompt)
        if base_url:
            prefixed_model_name = f"ollama/{model_name}"
            llm_config = {
                "model": prefixed_model_name,
                "base_url": base_url,
                "temperature": config.model.temperature
            }
            with warnings.catch_warnings():
                warnings.simplefilter("ignore", DeprecationWarning)
                return Ollama(**llm_config)
        return None

    def get_llm_for_role(self, role: str) -> Optional[Ollama]:
        prompt = f"LLM selection for a {role} role."
        base_url, peer_name, model_name = self.get_optimal_endpoint_and_model(prompt)
        if base_url:
            prefixed_model_name = f"ollama/{model_name}"
            llm_config = {
                "model": prefixed_model_name,
                "base_url": base_url,
                "temperature": config.model.temperature
            }
            with warnings.catch_warnings():
                warnings.simplefilter("ignore", DeprecationWarning)
                return Ollama(**llm_config)
        return None


@lru_cache()
def get_distributed_router_dependency() -> DistributedRouter:
    """FastAPI dependency to provide a cached instance of the DistributedRouter."""
    return DistributedRouter(PeerDiscovery.get_instance())