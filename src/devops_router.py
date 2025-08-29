# src/devops_router.py

import logging
import os
import warnings
from typing import Optional, List, Tuple
from rich.console import Console
from distributed_router import DistributedRouter, PeerDiscovery
from langchain_community.llms.ollama import Ollama
from config import config

console = Console()
logger = logging.getLogger(__name__)


class DevOpsDistributedRouter(DistributedRouter):
    """
    An enhanced DistributedRouter with a fallback to a local model.
    This class is specific to the DevOps crew to ensure resilience.
    """

    def __init__(self, peer_discovery_instance: PeerDiscovery, fallback_model_name: str = "llama3.2:1b"):
        super().__init__(peer_discovery_instance)
        self.fallback_model_name = fallback_model_name
        self.local_ollama_base_url = os.getenv("OLLAMA_HOST", "http://ollama:11434")

    def _get_llm_with_fallback(self, prompt: str, category: Optional[str] = None) -> Optional[Ollama]:
        """
        Internal method to handle LLM retrieval with fallback logic.
        """
        # Embed the category hint into the prompt for the parent method
        enriched_prompt = f"{category or 'general'}: {prompt}" if category else prompt

        try:
            base_url, _, model_name = super().get_optimal_endpoint_and_model(enriched_prompt)
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
        # For tasks, the category is implicitly in the prompt or inferred.
        # This implementation lets the parent's logic handle model preference based on prompt keywords.
        return self._get_llm_with_fallback(prompt)

    def get_llm_for_role(self, role: str) -> Optional[Ollama]:
        category = ""
        if "coding" in role.lower() or "developer" in role.lower():
            category = "coding"
        elif "qa" in role.lower() or "quality" in role.lower():
            category = "qa"
        else:
            category = "general"

        prompt = f"LLM selection for a {role} role."
        return self._get_llm_with_fallback(prompt, category)

    def _get_local_llm(self, model_name: str) -> Optional[Ollama]:
        """Gets an Ollama LLM instance for a specific model running on localhost."""
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
    """
    Instantiates and returns a DevOpsDistributedRouter configured for internal, secure use.
    """
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
