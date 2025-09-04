# src/dependencies.py

# Import all modules here
from peer_discovery import peer_discovery
from src.distributed_router import DistributedRouter
#from ai_crew import AICrewManager
# Import AICrewManager lazily
def get_ai_crew_manager():
    from ai_crew import AICrewManager
    return AICrewManager

# Create single instances of shared classes
distributed_router_instance = DistributedRouter(peer_discovery)