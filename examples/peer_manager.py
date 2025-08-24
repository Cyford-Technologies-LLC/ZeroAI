#!/usr/bin/env python3
"""
Peer Network Manager

Manage your distributed AI network peers.
"""

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from distributed_router import distributed_router
from rich.console import Console
import argparse

console = Console()

def main():
    parser = argparse.ArgumentParser(description="Manage ZeroAI peer network")
    parser.add_argument("command", choices=["add", "list", "test"], help="Command to execute")
    parser.add_argument("--ip", help="IP address of peer to add")
    parser.add_argument("--port", type=int, default=8080, help="Port of peer (default: 8080)")
    parser.add_argument("--name", help="Name for the peer")
    parser.add_argument("--model", default="llama3.2:1b", help="Model to test with")
    
    args = parser.parse_args()
    
    console.print("🌐 [bold blue]ZeroAI Peer Network Manager[/bold blue]")
    console.print("=" * 50)
    
    if args.command == "add":
        if not args.ip:
            console.print("❌ IP address required for add command", style="red")
            return
        
        console.print(f"🔍 Adding peer: {args.ip}:{args.port}")
        success = distributed_router.add_peer(args.ip, args.port, args.name)
        
        if success:
            console.print("✅ Peer added successfully!", style="green")
        else:
            console.print("❌ Failed to add peer", style="red")
    
    elif args.command == "list":
        distributed_router.list_peers()
    
    elif args.command == "test":
        console.print(f"🧪 Testing network with model: {args.model}")
        endpoint, peer_name = distributed_router.get_optimal_endpoint(model=args.model)
        console.print(f"📍 Selected endpoint: {endpoint} ({peer_name})")

if __name__ == "__main__":
    main()