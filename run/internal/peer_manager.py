# /app/run/internal/peer_manager.py

import sys
import os
from pathlib import Path
import argparse
import requests
import time

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

# Fix: Import the class, not a specific instance
from distributed_router import DistributedRouter
from peer_discovery import peer_discovery, PeerNode
from rich.console import Console

console = Console()

def main():
    # Instantiate the DistributedRouter and PeerDiscovery here
    # This avoids circular import issues
    peer_discovery_instance = peer_discovery
    router_instance = DistributedRouter(peer_discovery_instance)

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
        # ... (rest of the add command)
    elif args.command == "list":
        # ... (rest of the list command)
    elif args.command == "status":
        list_peers_with_status()
    elif args.command == "test":
        if not args.ip:
            console.print(f"🧪 Testing optimal router logic with prompt: '{args.prompt}'", style="magenta")
            try:
                # Use the router_instance
                endpoint, peer_name, model_name = router_instance.get_optimal_endpoint_and_model(args.prompt)
                console.print(f"📍 Router Selected: [bold cyan]{peer_name}[/bold cyan] ({endpoint}) with Model: [bold yellow]{model_name}[/bold yellow]", style="green")
            except RuntimeError as e:
                console.print(f"❌ Failed to find optimal endpoint: {e}", style="red")
        else:
            console.print(f"🧪 Testing specific peer: {args.ip} with model: {args.model}")
            test_peer(args.ip, args.port, args.model)


if __name__ == "__main__":
    main()

# Add test_peer and list_peers_with_status functions below main
