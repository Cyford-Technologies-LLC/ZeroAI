import sys
import os
import argparse
from rich.console import Console
from typing import Optional, List
import warnings
import json

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
# Start discovery service immediately
peer_discovery_instance.start_discovery_service()

def run_test(router_type: str, prompt: str, ip: Optional[str] = None, model: Optional[str] = None):
    """
    Runs a test with the specified router configuration.
    """
    console.print(f"\n--- Running Test for Router: [bold cyan]{router_type}[/bold cyan] ---")
    console.print(f"Prompt: [yellow]'{prompt}'[/yellow]")

    llm_instance = None
    base_url, model_name = None, None

    # Determine the router instance
    router = None
    if router_type == 'distributed':
        router = DistributedRouter(peer_discovery_instance)
    elif router_type == 'devops':
        router = get_devops_router()

    # --- START DEBUG DUMP: Data sent to router ---
    console.print("\n--- DEBUG: Data Sent to Router ---", style="bold blue")
    console.print(f"  Prompt: '{prompt}'")
    preference_list = MODEL_PREFERENCES.get("default")
    console.print(f"  Model Preferences: {preference_list}")
    console.print(f"  PeerDiscovery Peers: {[p.name for p in peer_discovery_instance.get_peers()]}")
    console.print("--- END DEBUG: Data Sent to Router ---\n", style="bold blue")
    # --- END DEBUG DUMP ---

    try:
        if router:
            if router_type == 'distributed':
                rejects = []
                base_url, peer_name, model_name = router.get_optimal_endpoint_and_model(prompt, rejects)
                console.print(f"base url {base_url} {peer_name} {model_name}   {peer_discovery_instance.get_peers()}.", style="red")
            elif router_type == 'devops':
                # DevOps router has its own internal handling
                llm_instance = router.get_llm_for_task(prompt)
                if llm_instance:
                    base_url = llm_instance.base_url
                    model_name = llm_instance.model
        elif router_type == 'manual':
            if not ip or not model:
                console.print("[bold red]Error:[/bold red] Manual test requires an IP and model.", style="red")
                return

            base_url = f"http://{ip}:11434"
            model_name = model
            console.print(
                f"Attempting manual connection to IP: [bold green]{ip}[/bold green], Model: [bold yellow]{model}[/bold yellow]")
            llm_config = {
                "model": model,
                "base_url": base_url,
                "temperature": config.model.temperature
            }
            llm_instance = Ollama(**llm_config)

    except Exception as e:
        console.print(f"[bold red]Router or Connection Error:[/bold red] {e}", style="red")

    # --- START DEBUG DUMP: Data returned by router (or manual) ---
    console.print("\n--- DEBUG: Data Returned by Router ---", style="bold yellow")
    console.print(f"  Base URL: {base_url}")
    console.print(f"  Model Name: {model_name}")
    console.print("--- END DEBUG: Data Returned by Router ---\n", style="bold yellow")
    # --- END DEBUG DUMP ---

    if not llm_instance and model_name:
        llm_instance = Ollama(model=model_name, base_url=base_url, temperature=config.model.temperature)

    if llm_instance:
        console.print(
            f"[bold green]LLM Instance Created:[/bold green] Model='{llm_instance.model}', Base URL='{llm_instance.base_url}'",
            style="green")
        try:
            with warnings.catch_warnings():
                warnings.simplefilter("ignore", DeprecationWarning)
                test_prompt = f"Please provide a very short, one-sentence response to the following: {prompt}"
                response = llm_instance.invoke(test_prompt)
                console.print(f"\n[bold magenta]Test Response:[/bold magenta] {response}")
        except Exception as e:
            console.print(f"[bold red]LLM Invocation Error:[/bold red] {e}", style="red")
    else:
        console.print("[bold yellow]LLM Instance could not be created or retrieved.[/bold yellow]", style="yellow")


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

    # REMOVED: All waiting and timeout code is removed here
    # Just directly check peers
    peers = peer_discovery_instance.get_peers()
    console.print(f"Current peers: {[p.name for p in peers]}", style="cyan")
    
    router_type = 'devops'
    if args.distributed:
        router_type = 'distributed'
    elif args.manual:
        router_type = 'manual'

    run_test(router_type, args.prompt, args.ip, args.model)


if __name__ == '__main__':
    main()