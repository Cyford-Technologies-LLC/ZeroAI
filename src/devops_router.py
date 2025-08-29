# src/devops_router.py

import logging
import os
import warnings
from typing import Optional, List, Tuple
from rich.console import Console
# Import the global instance from the parent module
from distributed_router import DistributedRouter, peer_discovery_instance, MODEL_PREFERENCES, KEYWORDS_TO_CATEGORY, \
    MODEL_MEMORY_MAP
from langchain_community.llms.ollama import Ollama
from config import config

console = Console()
logger = logging.getLogger(__name__)


class DevOpsDistributedRouter(DistributedRouter):
    """
    An enhanced DistributedRouter with a fallback to a local model and role-based model preference.
    """

    def __init__(self, peer_discovery_instance: object, fallback_model_name: str = "llama3.2:1b"):
        # The __init__ is fine, it will use the instance provided.
        super().__init__(peer_discovery_instance)
        self.fallback_model_name = fallback_model_name
        self.local_ollama_base_url = os.getenv("OLLAMA_HOST", "http://ollama:11434")
# ... (rest of the class is fine)

def get_router():
    try:
        # Use the global instance from the imported module
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
