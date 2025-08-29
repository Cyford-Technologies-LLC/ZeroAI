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
            # Temporary debug log
            console.print(f"Attempting distributed routing for category '{category}' with preferences: {preference_list}", style="blue")

            # FIX: Call the parent's get_optimal_endpoint_and_model with the correct parameters
            base_url, _, model_name = super().get_optimal_endpoint_and_model(prompt,
                                                                             model_preference_list=preference_list)
            # Temporary debug log
            console.print(f"Distributed routing result: base_url={base_url}, model_name={model_name}", style="blue")


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
