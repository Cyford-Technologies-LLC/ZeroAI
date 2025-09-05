#!/usr/bin/env python3
"""
Manual GPU Setup Guide for ZeroAI

Shows how to configure ZeroAI with your Prime Intellect or other
manually managed GPU instances.
"""

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from rich.console import Console
from rich.panel import Panel
from rich.table import Table

console = Console()


def main():
    """Run manual GPU setup guide."""
    
    # Welcome message
    welcome_text = """🔧 Manual GPU Setup for ZeroAI

Since Prime Intellect doesn't provide direct API access, 
ZeroAI will work with your manually managed instances.

Setup Process:
1. Start your Prime Intellect instance manually
2. Install Ollama on the instance  
3. Get the instance endpoint URL
4. Configure ZeroAI to use that endpoint
5. ZeroAI routes complex tasks to your GPU automatically"""
    
    console.print(Panel(welcome_text, title="Manual GPU Setup", border_style="blue"))
    
    show_setup_steps()
    show_configuration_example()
    show_usage_example()


def show_setup_steps():
    """Show detailed setup steps."""
    console.print("\n📋 [bold]Setup Steps[/bold]")
    
    steps = [
        ("1", "Start Instance", "Go to https://app.primeintellect.ai/ and start your instance"),
        ("2", "Connect via SSH", "SSH into your instance: ssh root@your-instance-ip"),
        ("3", "Install Ollama", "curl -fsSL https://ollama.ai/install.sh | sh"),
        ("4", "Start Ollama", "ollama serve --host 0.0.0.0"),
        ("5", "Download Model", "ollama pull llama3.1:8b"),
        ("6", "Get Endpoint", "Note your instance's public IP or URL"),
        ("7", "Configure ZeroAI", "Set MANUAL_GPU_ENDPOINT in your .env file")
    ]
    
    table = Table()
    table.add_column("Step", style="cyan", width=4)
    table.add_column("Action", style="yellow", width=15)
    table.add_column("Details", style="white")
    
    for step, action, details in steps:
        table.add_row(step, action, details)
    
    console.print(table)


def show_configuration_example():
    """Show configuration example."""
    console.print("\n⚙️  [bold]Configuration Example[/bold]")
    
    config_example = """# In your .env file:
MANUAL_GPU_ENABLED=true
MANUAL_GPU_NAME=Prime Intellect RTX 3070
MANUAL_GPU_ENDPOINT=http://your-instance-ip:11434

# Or if using port forwarding:
MANUAL_GPU_ENDPOINT=http://ollama:11434

# ZeroAI settings:
GPU_ACCESS_ENABLED=true
GPU_PROVIDER_PRIORITY=manual
THUNDER_COMPLEXITY_THRESHOLD=7"""
    
    console.print(Panel(config_example, title="Configuration", border_style="green"))


def show_usage_example():
    """Show usage example."""
    console.print("\n🚀 [bold]Usage Example[/bold]")
    
    usage_example = """from src.zeroai import ZeroAI

# Initialize ZeroAI with manual GPU
zero = ZeroAI(mode="smart")

# Simple tasks → Local (free)
result1 = zero.chat("Hello!")

# Complex tasks → Your Prime Intellect GPU ($0.16/hr)
result2 = zero.analyze("Comprehensive market analysis")

# ZeroAI automatically routes based on complexity!"""
    
    console.print(Panel(usage_example, title="Python Usage", border_style="cyan"))
    
    console.print("\n💡 [bold yellow]Pro Tips:[/bold yellow]")
    console.print("• Keep your Prime Intellect instance running during work sessions")
    console.print("• Use SSH port forwarding for secure connections")
    console.print("• Monitor costs in Prime Intellect dashboard")
    console.print("• Adjust complexity threshold to control GPU usage")
    
    console.print("\n🔗 [bold]Useful Commands:[/bold]")
    console.print("• SSH: ssh -L 11434:ollama:11434 root@your-instance")
    console.print("• Test: curl http://ollama:11434/api/tags")
    console.print("• Monitor: python examples/zeroai_demo.py")


if __name__ == "__main__":
    main()