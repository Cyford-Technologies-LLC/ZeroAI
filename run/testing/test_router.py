import sys
import os
import argparse
from rich.console import Console
from typing import Optional, List
import warnings
import json
from time import sleep, time
import threading

# Adjust the Python path to import modules from the parent directory
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../../src')))

from langchain_community.llms.ollama import Ollama
from distributed_router import DistributedRouter, PeerDiscovery, MODEL_PREFERENCES
from devops_router import DevOpsDistributedRouter, get_router as get_devops_router
from config import config
from peer_discovery import PeerDiscovery

console = Console()

# Instantiate the PeerDiscovery once
peer_discovery_instance = PeerDiscovery()


def run_test(router_type: str, prompt: str, ip: Optional[str] = None, model: Optional[str] = None):


# ... (rest of run_test, unchanged) ...

def main():
    parser = argparse.ArgumentParser(description="Test different LLM routing strategies.")

    router_group = parser.add_mutually_exclusive_group(required=True)
    router_group.add_argument('-d', '--distributed', action='store_true', help="Test the standard distributed router.")
    router_group.add_argument('-dv', '--devops', action='store_true', help="Test the new devops router (default).")
    router_group.add_argument('-m', '--manual', action='store_true',
                              help="Test a manual configuration with IP and model.")

    parser.add_argument('--ip', type=str, help="IP address for manual testing.")
    parser.add_argument('--model', type=str, help="Model name for manual testing.")

    parser.add_argument('--prompt', type=str, required=True, help="The prompt to test the LLM with.")

    args = parser.parse_args()

    # FIX: Robustly wait for peer discovery to complete
    start_time = time()
    timeout = 15  # Wait for a maximum of 15 seconds
    console.print(f"Waiting up to {timeout} seconds for initial peer discovery...", style="yellow")
    while not peer_discovery_instance.get_peers() and (time() - start_time) < timeout:
        console.print("  - Waiting for peers...", style="dim")
        sleep(1)

    if not peer_discovery_instance.get_peers():
        console.print(f"[bold red]Error:[/bold red] Initial peer discovery timed out. No peers available.", style="red")
        return

    console.print(f"✅ Initial peer discovery successful. Found {len(peer_discovery_instance.get_peers())} peers.",
                  style="green")

    router_type = 'devops'
    if args.distributed:
        router_type = 'distributed'
    elif args.manual:
        router_type = 'manual'

    run_test(router_type, args.prompt, args.ip, args.model)


if __name__ == '__main__':
    main()
