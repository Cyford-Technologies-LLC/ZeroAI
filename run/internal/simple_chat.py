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

# Import all necessary components
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
        # --- FIX 1: Explicitly define the model for chat ---
        # Use a model known to exist on the peer and be suitable for chat.
        model_name = "llama3.2:latest"  # You can also use "codellama:13b"

        # Instantiate the router and find the correct endpoint for our model
        peer_discovery_instance = PeerDiscovery()
        router = DistributedRouter(peer_discovery_instance)

        ollama_url, peer_name, _ = router.get_optimal_endpoint_and_model(model_name)

        # Print the selected peer and model for clarity
        console.print(f"🤖 Using model: [bold green]{model_name}[/bold green] on peer: [bold cyan]{peer_name}[/bold cyan]")

        # --- FIX 2: Initialize the LLM explicitly ---
        # Initialize the ChatOllama LLM with the specific model and URL
        llm = ChatOllama(
            model=model_name,
            base_url=ollama_url,
            temperature=config.model.temperature
        )

        # Initialize the AICrewManager with the pre-configured LLM
        # This bypasses the automatic model selection for the "chat" category
        manager = AICrewManager(llm, category="chat")

        while True:
            question = input("\n❓ Ask me anything (or 'quit' to exit): ").strip()

            if question.lower() in ['quit', 'exit', 'q']:
                break

            if not question:
                continue

            console.print(f"\n🤔 Thinking about: [green]{question}[/green]")

            try:
                # Call the LLM directly through the manager
                result = manager.llm.invoke(f"Answer this question concisely: {question}")

                console.print(f"\n💡 [bold green]Answer:[/bold green]\n{result.content}")

            except Exception as e:
                console.print(f"❌ Error during LLM call: {e}")

    except KeyboardInterrupt:
        console.print("\n👋 Goodbye!")
    except Exception as e:
        console.print(f"❌ Error: {e}")

if __name__ == "__main__":
    main()
