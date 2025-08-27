#!/usr/bin/env python3
"""
Peer Network Manager

Manage your distributed AI network peers.
"""

import sys
import os
from pathlib import Path
import argparse
import requests
import time

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from distributed_router import distributed_router
from rich.console import Console
from peer_discovery import peer_discovery, PeerNode

console = Console()

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

def main():
    parser = argparse.ArgumentParser(description="Manage ZeroAI peer network")
    parser.add_argument("command", choices=["add", "list", "test"], help="Command to execute")
    parser.add_argument("--ip", help="IP address of peer to add")
    parser.add_argument("--port", type=int, default=8080, help="Port of peer (default: 8080)")
    parser.add_argument("--name", help="Name for the peer")
    parser.add_argument("--model", default="llama3.2:1b", help="Model to test with (e.g., test command)")

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

    elif args.command == "list":
        # Fix: Call the list_peers method on the peer_discovery instance
        peer_discovery.list_peers()

    elif args.command == "test":
        if not args.ip:
            console.print(f"🧪 Testing optimal router logic with model: {args.model}")
            try:
                endpoint, peer_name, model_name = distributed_router.get_optimal_endpoint_and_model(f"test using model {args.model}")
                console.print(f"📍 Selected endpoint: {endpoint} ({peer_name}, model: {model_name})", style="cyan")
            except RuntimeError as e:
                console.print(f"❌ Failed to find optimal endpoint: {e}", style="red")
        else:
            console.print(f"🧪 Testing specific peer: {args.ip} with model: {args.model}")
            test_peer(args.ip, args.port, args.model)


if __name__ == "__main__":
    main()
