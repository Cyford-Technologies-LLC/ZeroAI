#!/usr/bin/env python3
"""
Quick Start Script for Self-Hosted Agentic AI

This script provides an easy way to get started with the AI system.
"""

import subprocess
import sys
import os
from pathlib import Path
from rich.console import Console
from rich.panel import Panel
from rich.text import Text

console = Console()


def check_ollama():
    """Check if Ollama is running."""
    try:
        import requests
        response = requests.get("http://olloma:11434/api/tags", timeout=5)
        return response.status_code == 200
    except:
        return False


def check_model():
    """Check if the required model is available."""
    try:
        import requests
        response = requests.get("http://olloma:11434/api/tags", timeout=5)
        if response.status_code == 200:
            models = response.json().get("models", [])
            return any("llama3.1" in model.get("name", "") for model in models)
    except:
        pass
    return False


def main():
    """Main startup function."""
    # Display welcome message
    welcome_text = Text()
    welcome_text.append("🤖 Self-Hosted Agentic AI\n", style="bold blue")
    welcome_text.append("Build your own AI workforce that runs entirely on your hardware!", style="green")
    
    console.print(Panel(welcome_text, title="Welcome", border_style="blue"))
    
    # Check system status
    console.print("\n🔍 [bold]Checking system status...[/bold]")
    
    # Check if Ollama is running
    if not check_ollama():
        console.print("❌ Ollama server is not running")
        console.print("💡 Please start Ollama: [bold cyan]ollama serve[/bold cyan]")
        return
    
    console.print("✅ Ollama server is running")
    
    # Check if model is available
    if not check_model():
        console.print("❌ Llama 3.1 model not found")
        console.print("💡 Please download the model: [bold cyan]ollama pull llama3.1:8b[/bold cyan]")
        return
    
    console.print("✅ Llama 3.1 model is available")
    
    # Show available examples
    console.print("\n📚 [bold]Available Examples:[/bold]")
    console.print("1. 🔍 Basic Research Crew")
    console.print("2. 🧠 Advanced Analysis Crew")
    console.print("3. 📖 View Documentation")
    console.print("4. ⚙️  Configuration")
    
    choice = input("\n🎯 Select an option (1-4): ").strip()
    
    if choice == "1":
        console.print("\n🚀 Starting Basic Research Crew...")
        env = os.environ.copy()
        env['PYTHONPATH'] = str(Path.cwd() / "src")
        subprocess.run([sys.executable, "examples/basic_crew.py"], env=env)
    elif choice == "2":
        console.print("\n🚀 Starting Advanced Analysis Crew...")
        env = os.environ.copy()
        env['PYTHONPATH'] = str(Path.cwd() / "src")
        subprocess.run([sys.executable, "examples/advanced_analysis.py"], env=env)
    elif choice == "3":
        console.print("\n📖 Opening documentation...")
        console.print("📁 Check the docs/ folder for comprehensive guides:")
        console.print("   • docs/setup.md - Complete setup guide")
        console.print("   • README.md - Project overview")
        console.print("   • CONTRIBUTING.md - How to contribute")
    elif choice == "4":
        console.print("\n⚙️  Configuration file: config/settings.yaml")
        console.print("📝 Edit this file to customize your AI setup")
    else:
        console.print("\n🔍 Running Basic Research Crew (default)...")
        env = os.environ.copy()
        env['PYTHONPATH'] = str(Path.cwd() / "src")
        subprocess.run([sys.executable, "examples/basic_crew.py"], env=env)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        console.print("\n👋 Goodbye!")
    except Exception as e:
        console.print(f"\n❌ Error: {e}")
        console.print("💡 Check the setup guide: docs/setup.md")