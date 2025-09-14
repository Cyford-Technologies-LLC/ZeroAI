# src/learning/adaptive_router.py

from typing import Optional, List, Tuple, Dict, Any
# from src.distributed_router import DistributedRouter
# from peer_discovery import PeerDiscovery
from learning.feedback_loop import FeedbackLoop
import logging
from rich.console import Console

console = Console()
logger = logging.getLogger(__name__)

class AdaptiveRouter:
    """Router that learns from past interactions and adapts its routing decisions."""
    
    def __init__(self, peer_discovery_instance=None):
        # super().__init__(peer_discovery_instance)
        self.feedback_loop = FeedbackLoop()
        self.learned_category_mapping = {}
        self.refresh_learned_mappings()
    
    def refresh_learned_mappings(self):
        """Update the learned mappings from the feedback system"""
        # Get learned model preferences for categories
        learned_model_preferences = self.feedback_loop.get_category_model_mapping()
        
        if learned_model_preferences:
            console.print("🧠 Using learned model preferences for categories:", style="green")
            for category, models in learned_model_preferences.items():
                console.print(f"  {category}: {models}", style="cyan")
            self.learned_category_mapping = learned_model_preferences
    
    def get_optimal_endpoint_and_model(self, prompt: str, failed_peers: Optional[List[str]] = None,
                                      model_preference_list: Optional[List[str]] = None) -> Tuple[
        Optional[str], Optional[str], Optional[str]]:
        
        # Determine category from prompt
        from distributed_router import KEYWORDS_TO_CATEGORY
        prompt_lower = prompt.lower()
        category = next((cat for key, cat in KEYWORDS_TO_CATEGORY.items() if key in prompt_lower), "default")
        
        # Use learned model preferences if available for this category
        if category in self.learned_category_mapping and self.learned_category_mapping[category]:
            learned_preferences = self.learned_category_mapping[category]
            console.print(f"🧠 Using learned model preferences for {category}: {learned_preferences}", style="green")
            model_preference_list = learned_preferences
        
        # Call the parent implementation with our (potentially modified) preferences
        return super().get_optimal_endpoint_and_model(prompt, failed_peers, model_preference_list)