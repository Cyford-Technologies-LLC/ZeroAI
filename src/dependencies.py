# src/dependencies.py

# Import all modules here
from peer_discovery import peer_discovery
from distributed_router import DistributedRouter
from ai_crew import AICrewManager
from cache_manager import cache

# Create single instances of shared classes
distributed_router_instance = DistributedRouter(peer_discovery)