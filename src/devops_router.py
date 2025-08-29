# src/devops_router.py

import logging
import os
import warnings
from typing import Optional, List, Tuple
from rich.console import Console
from distributed_router import DistributedRouter, PeerDiscovery, MODEL_PREFERENCES, KEYWORDS_TO_CATEGORY, \
    MODEL_MEMORY_MAP
from langchain_community.llms.ollama import Ollama
from config import config
import json  # Ensure json is imported for better logging
import requests  # For making diagnostic API calls

console = Console()
logger = logging.getLogger(__name__)


class DevOpsDistributedRouter(DistributedRouter):
    """
    An enhanced DistributedRouter with a fallback to a local model and role-based model preference.
    """

    def __init__(self, peer_discovery_instance: PeerDiscovery, fallback_model_name: str = "llama3.2:1b"):
        super().__init__(peer_discovery_instance)
        self.fallback_model_name = fallback_model_name
        self.local_ollama_base_url = os.getenv("OLLAMA_HOST", "http://ollama:11434")

    def _determine_category_from_prompt(self, prompt: str) -> Optional[str]:
        prompt_lower = prompt.lower()
        for keyword, category in KEYWORDS_TO_CATEGORY.items():
            if keyword in prompt_lower:
                return category
        return None

    def _get_llm_with_fallback(self, prompt: str, category: Optional[str] = None,
                               model_preferences: Optional[List[str]] = None) -> Optional[Ollama]:
        try:
            if not category:
                category = self._determine_category_from_prompt(prompt) or "general"

            preference_list = model_preferences if model_preferences else MODEL_PREFERENCES.get(category,
                                                                                                MODEL_PREFERENCES[
                                                                                                    "default"])
            console.print(
                f"Attempting distributed routing for category '{category}' with preferences: {preference_list}",
                style="blue")

            # --- START DIAGNOSTICS: Dump what the router sees ---
            console.print("\n--- DEBUG: DevOpsRouter's view of PeerDiscovery ---", style="bold blue")
            peers_info = self.peer_discovery.get_peers()
            console.print(f"  Discovered Peers: {len(peers_info)}")
            for peer in peers_info:
                console.print(
                    f"  - Peer: [bold cyan]{peer.name}[/bold cyan] at [bold green]{peer.ip}:{peer.port}[/bold green]")
                console.print(f"    Capabilities: {json.dumps(peer.capabilities.model_dump(), indent=4, default=str)}")

                # Optional: Direct API call to verify the peer's reported models
                try:
                    ollama_api_url = f"http://{peer.ip}:{peer.port}/api/tags"
                    response = requests.get(ollama_api_url, timeout=5)
                    response.raise_for_status()
                    remote_models = [m['name'] for m in response.json().get('models', [])]
                    console.print(
                        f"    [bold yellow]API Check:[/bold yellow] Found {len(remote_models)} models via API: {remote_models}")
                except requests.exceptions.RequestException as e:
                    console.print(f"    [bold red]API Check:[/bold red] Failed to connect to Ollama API: {e}")
            console.print("--- END DIAGNOSTICS ---\n", style="bold blue")
            # --- END DIAGNOSTICS ---

            # Attempt distributed routing
            base_url, _, model_name = None, None, None
            try:
                base_url, _, model_name = super().get_optimal_endpoint_and_model(prompt,
                                                                                 model_preference_list=preference_list)
            except Exception as e:
                console.print(f"DEBUG: Call to parent's distributed routing method failed with error: {e}", style="red")

            console.print(f"DEBUG: Distributed routing result: base_url={base_url}, model_name={model_name}",
                          style="blue")

            if model_name:
                prefixed_model_name = f"ollama/{model_name}"
                llm_config = {"model": prefixed_model_name, "base_url": base_url,
                              "temperature": config.model.temperature}

                console.print("\n--- DEBUG: Successful Distributed Call ---", style="bold green")
                console.print(f"  Attempting to create Ollama instance with config:")
                console.print(f"  {json.dumps(llm_config, indent=2)}")
                console.print("--- END DEBUG: Successful Distributed Call ---\n", style="bold green")

                with warnings.catch_warnings():
                    warnings.simplefilter("ignore", DeprecationWarning)
                    return Ollama(**llm_config)
            else:
                # Trigger fallback if no model_name was returned
                console.print("⚠️ Distributed router failed to find a model. Falling back...", style="yellow")
                return self._get_local_llm(self.fallback_model_name)

        except Exception as e:
            console.print(f"❌ An unexpected error occurred: {e}", style="red")
            return None

    # ... (rest of the class remains unchanged)


def get_router():
    try:
        # Revert to original instantiation
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
