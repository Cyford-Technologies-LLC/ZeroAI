#!/usr/bin/env python3
"""
Basic Ollama Chat Example

A simple script to interact with Ollama models without dependencies on the ZeroAI framework.
"""

import sys
import os
import time
import requests
from pathlib import Path
from rich.console import Console

console = Console()

def get_available_models():
    """Get a list of available models from Ollama."""
    try:
        response = requests.get("http://ollama:11434/api/tags")
        if response.status_code == 200:
            models = [model["name"] for model in response.json()["models"]]
            return models
        else:
            console.print("⚠️ Could not fetch available models", style="yellow")
            return []
    except Exception as e:
        console.print(f"⚠️ Error getting models: {e}", style="yellow")
        return []

def chat_with_model(model_name, message):
    """Chat with an Ollama model."""
    try:
        console.print(f"🤖 Sending message to {model_name}...", style="blue")

        response = requests.post(
            "http://ollama:11434/api/generate",
            json={
                "model": model_name,
                "prompt": message,
                "stream": False
            },
            timeout=60
        )

        if response.status_code == 200:
            return response.json().get("response", "No response received")
        else:
            console.print(f"❌ Error: {response.status_code} - {response.text}", style="red")
            return None
    except Exception as e:
        console.print(f"❌ Error: {e}", style="red")
        return None

def main():
    console.print("🚀 [bold blue]Simple Ollama Chat[/bold blue]")
    console.print("=" * 50)

    # Get list of available models
    available_models = get_available_models()

    if not available_models:
        console.print("❌ No models found. Please run 'ollama pull llama3.2:1b' first", style="red")
        console.print("💡 Make sure Ollama is running with: 'ollama serve'", style="yellow")
        return

    # Display available models
    console.print("\n📋 Available models:")
    for i, model in enumerate(available_models, 1):
        console.print(f"  {i}. {model}")

    # Select a model
    default_model = available_models[0]
    console.print(f"\n🤖 Using default model: [bold blue]{default_model}[/bold blue]")

    # Get user message
    console.print("\n💬 Enter your message (or press Enter for default):")
    message = input().strip()

    if not message:
        message = "Tell me about who you are"

    # Start timer
    start_time = time.time()

    # Get response
    response = chat_with_model(default_model, message)

    # End timer
    end_time = time.time()
    generation_time = end_time - start_time

    if response:
        console.print("\n" + "=" * 50)
        console.print("🤖 [bold green]Response:[/bold green]")
        console.print("=" * 50)
        print(response)
        console.print(f"\n⏱️ Generation time: {generation_time:.2f} seconds", style="cyan")

        # Save to file
        output_file = Path("output") / f"chat_{int(time.time())}.txt"
        output_file.parent.mkdir(exist_ok=True)

        with open(output_file, 'w', encoding='utf-8') as f:
            f.write(f"Message: {message}\n\nResponse:\n{response}")

        console.print(f"\n💾 Chat saved to: [bold blue]{output_file}[/bold blue]")

if __name__ == "__main__":
    main()