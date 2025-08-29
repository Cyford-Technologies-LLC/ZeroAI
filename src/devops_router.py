# src/devops_router.py

import logging
import os
import warnings
from typing import Optional, List, Tuple
from rich.console import Console
# FIX: Import the original DistributedRouter, PeerDiscovery, and Ollama classes
from distributed_router import DistributedRouter, PeerDiscovery
from langchain_community.llms.ollama import Ollama
from config import config

console = Console()
logger = logging.getLogger(__name__)


# FIX: Define a new class that inherits from DistributedRouter
class DevOpsDistributedRouter(DistributedRouter):
    """
    An enhanced DistributedRouter with a fallback to a local model.
    This class is specific to the DevOps crew to ensure resilience.
    """

    def __init__(self, peer_discovery_instance: PeerDiscovery, fallback_model_name: str = "llama3.2:1b"):
        # FIX: Explicitly call the parent constructor
        super().__init__(peer_discovery_instance)
        self.fallback_model_name = fallback_model_name
        # FIX: Ensure a local Ollama base URL is always available
        self.local_ollama_base_url = os.getenv("OLLAMA_HOST", "http://ollama:11434")

    # FIX: Override get_llm_for_task to add fallback logic
    def get_llm_for_task(self, prompt: str) -> Optional[Ollama]:
        try:
            # First, attempt to use the existing distributed routing logic
            base_url, _, model_name = self.get_optimal_endpoint_and_model(prompt)
            if model_name:
                prefixed_model_name = f"ollama/{model_name}"
                llm_config = {"model": prefixed_model_name, "base_url": base_url,
                              "temperature": config.model.temperature}
                with warnings.catch_warnings():
                    warnings.simplefilter("ignore", DeprecationWarning)
                    return Ollama(**llm_config)
            else:
                # Raise an error if distributed routing fails to force the fallback
                raise RuntimeError("Distributed routing failed and no model name was returned.")
        except Exception as e:
            console.print(
                f"⚠️ Distributed routing failed: {e}. Falling back to local model '{self.fallback_model_name}'.",
                style="yellow")
            return self._get_local_llm(self.fallback_model_name)

    # FIX: Override get_llm_for_role to add fallback logic
    def get_llm_for_role(self, role: str) -> Optional[Ollama]:
        prompt = ""
        if "coding" in role.lower() or "developer" in role.lower():
            prompt = "Please provide code or resolve a coding issue."
        else:
            prompt = "General purpose query."

        try:
            # First, attempt to use the existing distributed routing logic
            base_url, _, model_name = self.get_optimal_endpoint_and_model(prompt)
            if model_name:
                prefixed_model_name = f"ollama/{model_name}"
                llm_config = {"model": prefixed_model_name, "base_url": base_url,
                              "temperature": config.model.temperature}
                with warnings.catch_warnings():
                    warnings.simplefilter("ignore", DeprecationWarning)
                    return Ollama(**llm_config)
            else:
                # Raise an error if distributed routing fails to force the fallback
                raise RuntimeError("Distributed routing failed and no model name was returned.")
        except Exception as e:
            console.print(
                f"⚠️ Distributed routing failed for role '{role}': {e}. Falling back to local model '{self.fallback_model_name}'.",
                style="yellow")
            return self._get_local_llm(self.fallback_model_name)

    # FIX: Add a helper method for local LLM creation
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
        # FIX: Instantiate the new DevOpsDistributedRouter class instead
        router = DevOpsDistributedRouter(peer_discovery_instance, fallback_model_name="llama3.2:1b")
        logger.info("Secure internal DevOps router successfully instantiated with local fallback.")
        return router
    except Exception as e:
        logger.critical(f"FATAL: Failed to instantiate secure internal router: {e}")
        raise RuntimeError("Failed to get secure internal router setup.") from e


if __name__ == '__main__':
    # Test the router setup
    try:
        router = get_router()
        print("Secure internal router setup test successful.")
    except Exception as e:
        print(f"Secure internal router setup test failed: {e}")

