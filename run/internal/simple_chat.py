#!/usr/bin/env python3
"""
Simple Chat Example - Fast Local Processing
Single agent for quick responses
"""

import sys
import os
from pathlib import Path
from rich.console import Console

# Import necessary components
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))
from ai_crew import AICrewManager
from distributed_router import distributed_router
from rich.console import Console
from langchain_core.messages import HumanMessage, SystemMessage  # New import

console = Console()

def main():
    """Run simple chat example."""
    console.print("💬 [bold blue]ZeroAI Simple Chat[/bold blue]")
    console.print("=" * 40)

    try:
        # Provide a task description that maps to a working model
        # Use a model known to exist on the peer and be suitable for chat.
        manager = AICrewManager(distributed_router, category="chat", task="llama3.2:latest")

        # Define the system message for the bot's persona
        system_message = "You are a chatbot named Tony. You work for the Tiger company. Greet the user and answer their questions concisely."

        # The llm object is available from the AICrewManager instance
        llm = manager.llm

        # Print the initial greeting from the bot
        console.print(f"\n💡 [bold green]Tony:[/bold green] Hi! I'm Tony, and I work for the Tiger company. How can I help you today?")

        while True:
            question = input("\n❓ Ask me anything (or 'quit' to exit): ").strip()

            if question.lower() in ['quit', 'exit', 'q']:
                break

            if not question:
                continue

            console.print(f"\n🤔 Thinking about: [green]{question}[/green]")

            try:
                # Use a list of message objects for the LLM call
                messages = [
                    SystemMessage(content=system_message),
                    HumanMessage(content=question)
                ]

                result = llm.invoke(messages)

                console.print(f"\n💡 [bold green]Tony:[/bold green]\n{result.content}")

            except Exception as e:
                console.print(f"❌ Error during LLM call: {e}")

        console.print("\n👋 Goodbye!")

    except KeyboardInterrupt:
        console.print("\n👋 Goodbye!")
    except Exception as e:
        console.print(f"❌ Error: {e}")

if __name__ == "__main__":
    main()

