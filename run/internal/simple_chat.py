#!/usr/bin/env python3
"""
Simple Chat Example - Fast Local Processing
Single agent for quick responses
"""

import sys
import os
from pathlib import Path
from rich.console import Console

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

# Import required components
from ai_crew import AICrewManager
from distributed_router import distributed_router, DistributedRouter
from peer_discovery import PeerDiscovery
from langchain_ollama import ChatOllama
from config import config

console = Console()

def main():
    """Run simple chat example."""
    console.print("💬 [bold blue]ZeroAI Simple Chat[/bold blue]")
    console.print("=" * 40)

    try:
        # Instantiate the router
        peer_discovery_instance = PeerDiscovery()
        # distributed_router is a global singleton, but for clarity,
        # let's assume a proper instance is used.
        router = DistributedRouter(peer_discovery_instance)

        # --- FIX 1: Explicitly define the model for chat ---
        # The code generator works because it specifies a model.
        # The chat tool needs to do the same.
        model_name = "codellama:13b"  # Or another suitable chat model on your peer.

        # --- FIX 2: Find a suitable peer and endpoint BEFORE initializing the manager ---
        # The manager needs a valid LLM. The distributed_router can find this for us.
        ollama_url, peer_name, _ = router.get_optimal_endpoint_and_model(model_name)

        # Print the selected peer and model for clarity
        console.print(f"🤖 Using model: [bold green]{model_name}[/bold green] on peer: [bold cyan]{peer_name}[/bold cyan]")

        # --- FIX 3: Initialize the LLM explicitly ---
        # Initialize the ChatOllama LLM with the specific model and URL
        llm = ChatOllama(
            model=model_name,
            base_url=ollama_url,
            temperature=config.model.temperature
        )

        # Initialize the AICrewManager with the configured LLM
        manager = AICrewManager(llm, category="chat")

        while True:
            question = input("\n❓ Ask me anything (or 'quit' to exit): ").strip()

            if question.lower() in ['quit', 'exit', 'q']:
                break

            if not question:
                continue

            console.print(f"\n🤔 Thinking about: [green]{question}[/green]")

            # The AICrewManager's call method can be used, which wraps the LLM call.
            # Make sure your AICrewManager is properly configured to use the LLM.
            # Or, for a direct call:
            result = llm.invoke(f"Answer this question concisely: {question}")

            console.print(f"\n💡 [bold green]Answer:[/bold green]\n{result.content}")

    except KeyboardInterrupt:
        console.print("\n👋 Goodbye!")
    except Exception as e:
        console.print(f"❌ Error: {e}")

if __name__ == "__main__":
    main()

