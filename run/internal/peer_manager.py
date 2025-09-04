# /app/run/internal/peer_manager.py

import sys
import os
from pathlib import Path
import argparse
import requests
import time

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from src.distributed_router import DistributedRouter
from peer_discovery import peer_discovery, PeerNode
from rich.console import Console

console = Console()
def add_peer(self, ip: str, port: int, name: str) -> (bool, str):
    try:
        # Read existing peers or initialize if the file doesn't exist
        peers_data = self._load_peers_from_config()

        # Check if peer already exists to avoid duplicates
        if any(p['ip'] == ip for p in peers_data):
            return False, f"Peer with IP {ip} already exists."

        # Add the new peer
        new_peer = {"name": name, "ip": ip, "port": port}
        peers_data.append(new_peer)

        # Write the updated peer list back to the file
        with open(PEERS_CONFIG_PATH, 'w') as f:
            json.dump({"peers": peers_data}, f, indent=4)

        # Immediately trigger a discovery cycle to pick up the new peer
        self._discovery_cycle()

        return True, f"Successfully added peer {name} at {ip}:{port}."
    except Exception as e:
        return False, f"Failed to add peer: {e}"

def test_peer(ip, port, model):
    """Test connectivity to a specific peer and model."""
    try:
        ollama_url = f"http://{ip}:11434"
        console.print(f"🔍 Pinging peer {ip} for model '{model}'...", style="yellow")

        start_time = time.time()
        response = requests.get(f"{ollama_url}/api/tags", timeout=5)
        response.raise_for_status()

        models = [m['name'] for m in response.json().get('models', [])]
        if model in models:
            console.print(f"✅ Peer {ip} is available and has model '{model}'.", style="green")
        else:
            console.print(f"⚠️  Peer {ip} is available, but model '{model}' was not found.", style="yellow")
            console.print(f"   Available models: {', '.join(models)}", style="dim")

        end_time = time.time()
        console.print(f"⏱️  Test time: {end_time - start_time:.2f} seconds", style="cyan")

    except requests.exceptions.RequestException as e:
        console.print(f"❌ Failed to reach peer {ip}: {e}", style="red")


def list_peers_with_status():
    """Lists all discovered peers with their capabilities and status."""
    console.print("\n--- Discovered Peers Status ---", style="bold green")
    peers = peer_discovery.get_peers()

    if not peers:
        console.print("No peers discovered yet.", style="yellow")
        return

    for i, peer in enumerate(peers):
        status = "Active" if peer.capabilities.available else "Inactive/Unreachable"
        console.print(f"  [{i+1}] Peer Name: [bold cyan]{peer.name}[/bold cyan]", style="blue")
        console.print(f"      IP: {peer.ip}", style="white")
        console.print(f"      Status: {status}", style="white")
        console.print(f"      Resources:", style="white")
        console.print(f"        - Load Avg: {peer.capabilities.load_avg:.1f}%", style="white")
        console.print(f"        - RAM: {peer.capabilities.memory:.1f} GiB", style="white")
        if peer.capabilities.gpu_available:
            console.print(f"        - GPU Available: Yes", style="green")
            console.print(f"        - GPU Memory: {peer.capabilities.gpu_memory:.1f} GiB", style="green")
        else:
            console.print(f"        - GPU Available: No", style="red")
        console.print(f"        - Supported Models: {', '.join(peer.capabilities.models)}", style="white")
        console.print("-" * 40, style="dim")


def main():
    # Fix: Instantiate the DistributedRouter correctly if it's not a global singleton
    peer_discovery_instance = peer_discovery
    distributed_router_instance = DistributedRouter(peer_discovery_instance)

    parser = argparse.ArgumentParser(description="Manage ZeroAI peer network")
    parser.add_argument("command", choices=["add", "list", "test", "status"], help="Command to execute")
    parser.add_argument("--ip", help="IP address of peer to add")
    parser.add_argument("--port", type=int, default=8080, help="Port of peer (default: 8080)")
    parser.add_argument("--name", help="Name for the peer")
    parser.add_argument("--model", default="llama3.2:1b", help="Model to test with (e.g., test command)")
    parser.add_argument("--prompt", default="test", help="Prompt to use for the test command (e.g., 'test' command)")

    args = parser.parse_args()

    console.print("🌐 [bold blue]ZeroAI Peer Network Manager[/bold blue]")
    console.print("=" * 50)

    if args.command == "add":
        if not args.ip:
            console.print("❌ IP address required for add command", style="red")
            return

        console.print(f"🔍 Adding peer: {args.ip}:{args.port}")
        success, message = peer_discovery.add_peer(args.ip, args.port, args.name)

        if success:
            console.print(f"✅ {message}", style="green")
        else:
            console.print(f"❌ {message}", style="red")

    # FIX: Ensure indentation is correct here.
    elif args.command == "list":
        list_peers_with_status()

    elif args.command == "status":
        list_peers_with_status()

    elif args.command == "test":
        if not args.ip:
            console.print(f"🧪 Testing optimal router logic with prompt: '{args.prompt}'", style="magenta")
            try:
                endpoint, peer_name, model_name = distributed_router_instance.get_optimal_endpoint_and_model(args.prompt)
                console.print(f"📍 Router Selected: [bold cyan]{peer_name}[/bold cyan] ({endpoint}) with Model: [bold yellow]{model_name}[/bold yellow]", style="green")
            except RuntimeError as e:
                console.print(f"❌ Failed to find optimal endpoint: {e}", style="red")
        else:
            console.print(f"🧪 Testing specific peer: {args.ip} with model: {args.model}")
            test_peer(args.ip, args.port, args.model)


if __name__ == "__main__":
    main()
