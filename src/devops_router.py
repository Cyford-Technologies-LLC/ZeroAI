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

            # --- MODIFIED PARENT METHOD CALL ---
            base_url, peer_name, model_name = None, None, None
            try:
                # Fix: Pass empty failed_peers list and correctly pass model_preference_list
                base_url, peer_name, model_name = super().get_optimal_endpoint_and_model(
                    prompt=prompt,
                    failed_peers=[],  # Empty list for failed peers
                    model_preference_list=preference_list  # Pass the preference list correctly
                )
                console.print(f"Parent method returned: base_url={base_url}, peer_name={peer_name}, model_name={model_name}")
            except Exception as e:
                # Log the specific exception from the parent method
                console.print(f"DEBUG: Call to parent's distributed routing method failed with error: {e}", style="red")
            # --- END MODIFIED PARENT METHOD CALL ---

            console.print(f"DEBUG: Distributed routing result: base_url={base_url}, model_name={model_name}",
                          style="blue")

            if base_url and model_name:
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
                console.print("⚠️ Distributed router failed to find a model. Falling back to local model...", style="yellow")
                return self._get_local_llm(self.fallback_model_name)

        except Exception as e:
            # Catch any other, unexpected exceptions.
            console.print(f"❌ An unexpected error occurred in _get_llm_with_fallback: {e}", style="red")
            # Fall back to local model on any error
            console.print("Attempting to use local fallback model...", style="yellow")
            return self._get_local_llm(self.fallback_model_name)

    def get_llm_for_task(self, prompt: str) -> Optional[Ollama]:
        return self._get_llm_with_fallback(prompt)

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

            console.print("\n--- DEBUG: Local Fallback Call ---", style="bold yellow")
            console.print(f"  Creating Ollama instance with local config:")
            console.print(f"  {json.dumps(llm_config, indent=2)}")
            console.print("--- END DEBUG: Local Fallback Call ---\n", style="bold yellow")

            with warnings.catch_warnings():
                warnings.simplefilter("ignore", DeprecationWarning)
                console.print(
                    f"🔗 Using local LLM for '{model_name}' at [bold green]{self.local_ollama_base_url}[/bold green]",
                    style="blue")
                return Ollama(**llm_config)
        except Exception as e:
            console.print(f"❌ Failed to load local LLM '{model_name}': {e}", style="red")
            return None


def get_router():
    try:
        peer_discovery_instance = PeerDiscovery()
        peer_discovery_instance.start_discovery_service()  # Ensure discovery is running
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