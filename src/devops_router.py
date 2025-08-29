# src/devops_router.py

import logging
from distributed_router import DistributedRouter
from peer_discovery import PeerDiscovery

logger = logging.getLogger(__name__)

def get_router():
    """
    Instantiates and returns a DistributedRouter configured for internal, secure use.
    It includes a fallback to a local model if no suitable distributed peers are found.
    """
    try:
        peer_discovery_instance = PeerDiscovery()
        router = DistributedRouter(peer_discovery_instance)
        logger.info("Secure internal router successfully instantiated.")

        # FIX: Add a local model fallback configuration to the router
        # This will be used if the distributed peers fail to provide a suitable model.
        # This is a robust way to ensure a functional model is always available.
        try:
            router.add_local_model_fallback(model_name="llama3.2:1b")
            logger.info("Configured `llama3.2:1b` as the local model fallback.")
        except Exception as e:
            logger.error(f"Failed to add local model fallback: {e}")
            # The router can still function with peers, but log the failure.

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

