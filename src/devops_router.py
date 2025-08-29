# src/devops_router.py

import logging
import os
import warnings
from typing import Optional, List, Tuple
from rich.console import Console
from distributed_router import DistributedRouter, PeerDiscovery, MODEL_MEMORY_MAP
from langchain_community.llms.ollama import Ollama
from config import config

console = Console()
logger = logging.getLogger(__name__)

MODEL_PREFERENCES = {
    "developer": ["codellama:13b", "llama3.1:8b", "llama3.2:latest", "llama3.2:1b"],
    "research": ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llama3.2:1b"],
    "documentation": ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"],
    "devops_orchestrator": ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"],
    "repo_manager": ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"],
    "general": ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llava:7b", "llama3.2:1b"],
    "default": ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llava:7b", "llama3.2:1b"]
}


class DevOpsDistributedRouter(DistributedRouter):
    def __init__(self, peer_discovery_instance: PeerDiscovery, fallback_model_name: str = "llama3.2:1b"):
        super().__init__(peer_discovery_instance)
        self.fallback_model_name = fallback_model_name
        self.local_ollama_base_url = os.getenv("OLLAMA_HOST", "http://ollama:11434")

    def _get_llm_with_fallback(self, prompt: str, category: Optional[str] = None,
                               model_preferences: Optional[List[str]] = None) -> Optional[Ollama]:
        try:
            preference_list = model_preferences if model_preferences else MODEL_PREFERENCES.get(category,
                                                                                                MODEL_PREFERENCES[
                                                                                                    "default"])
            base_url, _, model_name = self.get_optimal_endpoint_and_model(prompt, model_preference_list=preference_list)
            if model_name:
                prefixed_model_name = f"ollama/{model_name}"
                llm_config = {"model": prefixed_model_name, "base_url": base_url,
                              "temperature": config.model.temperature}
                with warnings.catch_warnings():
                    warnings.simplefilter("ignore", DeprecationWarning)
                    return Ollama(**llm_config)
            else:
                raise RuntimeError("Distributed routing failed and no model name was returned.")
        except Exception as e:
            console.print(
                f"⚠️ Distributed routing failed for category '{category or 'general'}': {e}. Falling back to local model '{self.fallback_model_name}'.",
                style="yellow")
            return self._get_local_llm(self.fallback_model_name)

    def get_llm_for_task(self, prompt: str) -> Optional[Ollama]:
        return self._get_llm_with_fallback(prompt, category="general")

    def get_llm_for_role(self, role: str) -> Optional[Ollama]:
        role_category = "default"
        if any(r in role.lower() for r in ["coding", "developer"]):
            role_category = "developer"
        elif "research" in role.lower():
            role_category = "research"
        elif "documentation" in role.lower():
            role_category = "documentation"
        elif "orchestrator" in role.lower():
            role_category = "devops_orchestrator"
        elif "repo_manager" in role.lower():
            role_category = "repo_manager"

        model_preferences = MODEL_PREFERENCES.get(role_category, MODEL_PREFERENCES["default"])
        prompt = f"LLM selection for a {role} role."
        return self._get_llm_with_fallback(prompt, category=role_category, model_preferences=model_preferences)

    def _get_local_llm(self, model_name: str) -> Optional[Ollama]:
        try:
            prefixed_model_name = f"ollama/{model_name}"
            llm_config = {
                "model": prefixed_model_name,
                "base_url": self.local_ollama_base_url,
                "temperature": config.model.temperature
            }
            with warnings.catch_warnings():
                warnings.simplefilter("ignore", DeprecationWarning)
                console.print(
                    f"🔗 Using local LLM for '{model_name}' at [bold green]{self.local_ollama_base_url}[/bold green]",
                    style="blue")
                return Ollama(**llm_config)
        except Exception as e:
            console.print(f"❌ Failed to load local LLM '{model_name}': {e}", style="red")
            return None

    # FIX: Override the get_optimal_endpoint_and_model method to add robust logging and handling
    def get_optimal_endpoint_and_model(self, prompt: str, failed_peers: Optional[List[str]] = None,
                                       model_preference_list: Optional[List[str]] = None) -> Tuple[
        Optional[str], Optional[str], Optional[str]]:
        if failed_peers is None:
            failed_peers = []
        if model_preference_list is None:
            model_preference_list = MODEL_PREFERENCES.get("general")

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

            for model in model_preference_list:
                if model in available_models:
                    required_memory = MODEL_MEMORY_MAP.get(model)
                    if required_memory is None:
                        console.print(f"      - ⚠️ Skipping model {model}: memory requirements unknown.",
                                      style="yellow")
                        continue

                    # FIX: Correct memory comparison logic
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


def get_router():
    try:
        peer_discovery_instance = PeerDiscovery()
        router = DevOpsDistributedRouter(peer_discovery_instance, fallback_model_name="llama3.2:1b")
        logger.info("Secure internal DevOps router successfully instantiated with local fallback.")
        return router
    except Exception as e:
        logger.critical(f"FATAL: Failed to instantiate secure internal router: {e}")
        raise RuntimeError("Failed to get secure internal router setup.") from e


if __name__ == '__main__':
    try:
        router = get_router()
        print("Secure internal router setup test successful.")
    except Exception as e:
        print(f"Secure internal router setup test failed: {e}")
