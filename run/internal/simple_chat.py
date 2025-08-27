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

from ai_crew import AICrewManager
from distributed_router import distributed_router
from rich.console import Console

console = Console()

def main():
    """Run simple chat example."""
    console.print("💬 [bold blue]ZeroAI Simple Chat[/bold blue]")
    console.print("=" * 40)

    try:
        # --- FIX: Explicitly set a working model ---
        # The 'chat' category doesn't find a model, so we pass a working one.
        working_model = "llama3.2:latest"

        # Initialize with the working model, passing the router instance
        manager = AICrewManager(distributed_router, category="chat", task=working_model)

        while True:
            question = input("\n❓ Ask me anything (or 'quit' to exit): ").strip()

            if question.lower() in ['quit', 'exit', 'q']:
                break

            if not question:
                continue

            console.print(f"\n🤔 Thinking about: [green]{question}[/green]")

            try:
                # The AICrewManager is already configured with the correct LLM
                # and endpoint via the distributed_router.
                llm = manager.llm
                result = llm.call(f"Answer this question concisely: {question}")
                console.print(f"\n💡 [bold green]Answer:[/bold green]\n{result}")

            except Exception as e:
                console.print(f"❌ Error during LLM call: {e}")

        console.print("\n👋 Goodbye!")

    except KeyboardInterrupt:
        console.print("\n👋 Goodbye!")
    except Exception as e:
        console.print(f"❌ Error: {e}")

if __name__ == "__main__":
    main()

