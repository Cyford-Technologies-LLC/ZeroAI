# src/devops_router.py

import logging
from distributed_router import DistributedRouter
from peer_discovery import PeerDiscovery

logger = logging.getLogger(__name__)

def get_router():
    """
    Instantiates and returns a DistributedRouter configured for internal, secure use.
    """
    try:
        peer_discovery_instance = PeerDiscovery()
        router = DistributedRouter(peer_discovery_instance)
        logger.info("Secure internal router successfully instantiated.")
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
