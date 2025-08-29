# /opt/ZeroAI/src/distributed_router.py

# ... (imports)
import requests  # Ensure requests is imported
import json  # Ensure json is imported


# ... (other code)

class DistributedRouter:
    # ... (init and other methods)

    def get_optimal_endpoint_and_model(self, prompt: str, failed_peers: Optional[List[str]] = None,
                                       model_preference_list: Optional[List[str]] = None) -> Tuple[
        Optional[str], Optional[str], Optional[str]]:

        # ... (initial setup)

        console.print(f"🔎 Analyzing peers for task with model preference: {model_preference_list}", style="blue")

        # --- DEBUG LOGGING ---
        console.print(f"DEBUG: Discovered peers: {[p.name for p in all_peers]}", style="bold magenta")

        for peer in all_peers:
            if peer.name in failed_peers:
                console.print(f"   🚫 Skipping failed peer: {peer.name}", style="yellow")
                continue

            # --- DEBUG: Connection Check ---
            peer_ollama_url = f"http://{peer.ip}:{peer.port}"
            try:
                # Use a lightweight request to check if Ollama is responsive
                response = requests.get(f"{peer_ollama_url}/api/tags", timeout=5)
                response.raise_for_status()  # Raise an exception for bad status codes
                peer_models = [m['name'] for m in response.json().get('models', [])]
                console.print(
                    f"DEBUG: Successfully connected to peer [bold cyan]{peer.name}[/bold cyan]. Available models: {peer_models}",
                    style="green")
            except requests.exceptions.RequestException as e:
                console.print(
                    f"❌ DEBUG: Failed to connect to Ollama on peer [bold cyan]{peer.name}[/bold cyan] at {peer_ollama_url}: {e}",
                    style="red")
                continue  # Skip this peer

            available_models = peer_models

            # ... (the rest of the loop for model/memory checks)
            # You can add more debug logs here to show which model checks are failing
            # For example:
            for model in model_preference_list:
                if model in available_models:
                    console.print(f"DEBUG: Checking model {model} on peer {peer.name}", style="dim")
                    # ... (rest of the check)
                else:
                    console.print(f"DEBUG: Model {model} not found on peer {peer.name}", style="red")

        # ... (candidate sorting and return)
