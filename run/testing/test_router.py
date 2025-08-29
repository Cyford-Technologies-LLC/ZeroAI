import sys
import os
import argparse
from rich.console import Console
from typing import Optional, List
import warnings
import json


#examples
#python run/testing/test_router.py -d --prompt "What is a distributed system?"
#python run/testing/test_router.py -dv --prompt "Perform general maintenance and check project health."
#python run/testing/test_router.py -m --ip 149.36.1.65 --model llama3.1:8b --prompt "Explain the concept of LLM."




# Adjust the Python path to import modules from the parent directory
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../../src')))

from langchain_community.llms.ollama import Ollama
from distributed_router import DistributedRouter, PeerDiscovery, MODEL_PREFERENCES
from devops_router import DevOpsDistributedRouter, get_router as get_devops_router
from config import config

console = Console()

# Instantiate the PeerDiscovery once for consistent peer data
# This is a good practice to ensure all router instances have the same view of the network
peer_discovery_instance = PeerDiscovery()


def run_test(router_type: str, prompt: str, ip: Optional[str] = None, model: Optional[str] = None):
    """
    Runs a test with the specified router configuration.
    """
    console.print(f"\n--- Running Test for Router: [bold cyan]{router_type}[/bold cyan] ---")
    console.print(f"Prompt: [yellow]'{prompt}'[/yellow]")

    llm_instance = None
    try:
        if router_type == 'distributed':
            router = DistributedRouter(peer_discovery_instance)
            # Use a default category for the test if not specified
            preference_list = MODEL_PREFERENCES.get("default")
            base_url, _, model_name = router.get_optimal_endpoint_and_model(prompt,
                                                                            model_preference_list=preference_list)
            if model_name:
                llm_instance = Ollama(model=model_name, base_url=base_url, temperature=config.model.temperature)
        elif router_type == 'devops':
            router = get_devops_router()
            llm_instance = router.get_llm_for_task(prompt)
        elif router_type == 'manual':
            if not ip or not model:
                console.print("[bold red]Error:[/bold red] Manual test requires an IP and model.", style="red")
                return

            console.print(
                f"Attempting manual connection to IP: [bold green]{ip}[/bold green], Model: [bold yellow]{model}[/bold yellow]")
            llm_config = {
                "model": model,
                "base_url": f"http://{ip}:11434",  # Assuming port 11434 for manual Ollama
                "temperature": config.model.temperature
            }
            llm_instance = Ollama(**llm_config)

    except Exception as e:
        console.print(f"[bold red]Router or Connection Error:[/bold red] {e}", style="red")
        return

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

    # Use a mutually exclusive group for router selection
    router_group = parser.add_mutually_exclusive_group(required=True)
    router_group.add_argument('-d', '--distributed', action='store_true', help="Test the standard distributed router.")
    router_group.add_argument('-dv', '--devops', action='store_true', help="Test the new devops router (default).")
    router_group.add_argument('-m', '--manual', action='store_true',
                              help="Test a manual configuration with IP and model.")

    # Arguments for manual testing
    parser.add_argument('--ip', type=str, help="IP address for manual testing.")
    parser.add_argument('--model', type=str, help="Model name for manual testing.")

    parser.add_argument('--prompt', type=str, required=True, help="The prompt to test the LLM with.")

    args = parser.parse_args()

    # Determine router type based on arguments
    router_type = 'devops'  # Defaulting to devops
    if args.distributed:
        router_type = 'distributed'
    elif args.manual:
        router_type = 'manual'

    run_test(router_type, args.prompt, args.ip, args.model)


if __name__ == '__main__':
    main()
