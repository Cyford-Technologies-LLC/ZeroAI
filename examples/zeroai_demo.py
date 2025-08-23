#!/usr/bin/env python3
"""
ZeroAI Demo - Zero Cost. Zero Cloud. Zero Limits.

This demo showcases ZeroAI's intelligent cost optimization and
flexible deployment modes.
"""

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from zeroai import ZeroAI
from rich.console import Console
from rich.table import Table
from rich.panel import Panel

console = Console()


def main():
    """Run ZeroAI demo."""
    
    # Welcome message
    welcome_text = """💰 ZeroAI - Zero Cost. Zero Cloud. Zero Limits.

Experience the future of AI deployment:
• 🏠 Local Mode: Zero cost, complete privacy
• 🧠 Smart Mode: Optimal cost/performance balance  
• ☁️  Cloud Mode: Maximum power when needed
• 🎯 Intelligent routing based on task complexity"""
    
    console.print(Panel(welcome_text, title="Welcome to ZeroAI", border_style="blue"))
    
    # Initialize ZeroAI
    zero = ZeroAI(mode="smart")
    
    while True:
        # Show current status
        zero.show_status()
        
        # Show menu
        console.print("\n🎛️  [bold]ZeroAI Control Panel[/bold]")
        table = Table()
        table.add_column("Option", style="cyan")
        table.add_column("Description", style="white")
        
        table.add_row("1", "💬 Quick Chat")
        table.add_row("2", "🔍 Research Topic")
        table.add_row("3", "📊 Analyze Topic")
        table.add_row("4", "✍️  Write Article")
        table.add_row("5", "🏠 Switch to Local Mode (Zero Cost)")
        table.add_row("6", "🧠 Switch to Smart Mode (Optimal)")
        table.add_row("7", "☁️  Switch to Cloud Mode (Max Power)")
        table.add_row("8", "💰 Enable Cost Optimization")
        table.add_row("9", "🚀 Enable Performance Mode")
        table.add_row("0", "🚪 Exit")
        
        console.print(table)
        
        choice = input("\n🎯 Select option (0-9): ").strip()
        
        if choice == "1":
            quick_chat(zero)
        elif choice == "2":
            research_topic(zero)
        elif choice == "3":
            analyze_topic(zero)
        elif choice == "4":
            write_article(zero)
        elif choice == "5":
            zero.set_mode("local")
        elif choice == "6":
            zero.set_mode("smart")
        elif choice == "7":
            cloud_mode_setup(zero)
        elif choice == "8":
            zero.enable_cost_optimization()
        elif choice == "9":
            zero.enable_performance_mode()
        elif choice == "0":
            zero.cleanup()
            console.print("\n👋 Thank you for using ZeroAI!")
            break
        else:
            console.print("❌ Invalid option", style="red")


def quick_chat(zero: ZeroAI):
    """Quick chat with ZeroAI."""
    console.print("\n💬 [bold]ZeroAI Chat[/bold]")
    
    message = input("💬 Your message: ").strip()
    if not message:
        return
    
    try:
        console.print("🤖 ZeroAI is thinking...", style="yellow")
        response = zero.chat(message)
        
        console.print("\n🤖 [bold green]ZeroAI:[/bold green]")
        console.print(response)
        
    except Exception as e:
        console.print(f"❌ Error: {e}", style="red")


def research_topic(zero: ZeroAI):
    """Research a topic with ZeroAI."""
    console.print("\n🔍 [bold]ZeroAI Research[/bold]")
    
    topic = input("🔍 Research topic: ").strip()
    if not topic:
        return
    
    try:
        console.print(f"🔍 Researching: {topic}...", style="yellow")
        result = zero.research(topic)
        
        console.print("\n📊 [bold green]Research Results:[/bold green]")
        console.print(result[:500] + "..." if len(result) > 500 else result)
        
    except Exception as e:
        console.print(f"❌ Error: {e}", style="red")


def analyze_topic(zero: ZeroAI):
    """Analyze a topic with ZeroAI."""
    console.print("\n📊 [bold]ZeroAI Analysis[/bold]")
    
    topic = input("📊 Analysis topic: ").strip()
    if not topic:
        return
    
    try:
        console.print(f"📊 Analyzing: {topic}...", style="yellow")
        result = zero.analyze(topic)
        
        console.print("\n📈 [bold green]Analysis Results:[/bold green]")
        console.print(result[:500] + "..." if len(result) > 500 else result)
        
    except Exception as e:
        console.print(f"❌ Error: {e}", style="red")


def write_article(zero: ZeroAI):
    """Write an article with ZeroAI."""
    console.print("\n✍️  [bold]ZeroAI Writer[/bold]")
    
    topic = input("✍️  Article topic: ").strip()
    if not topic:
        return
    
    style = input("📝 Writing style (professional/casual/technical): ").strip() or "professional"
    
    try:
        console.print(f"✍️  Writing {style} article about: {topic}...", style="yellow")
        result = zero.write(topic, style)
        
        console.print("\n📝 [bold green]Article:[/bold green]")
        console.print(result[:600] + "..." if len(result) > 600 else result)
        
    except Exception as e:
        console.print(f"❌ Error: {e}", style="red")


def cloud_mode_setup(zero: ZeroAI):
    """Setup cloud mode."""
    console.print("\n☁️  [bold]Cloud Mode Setup[/bold]")
    
    providers = ["openai", "anthropic", "google"]
    console.print("Available providers:", ", ".join(providers))
    
    provider = input("☁️  Select provider (openai/anthropic/google): ").strip().lower()
    if provider not in providers:
        provider = "openai"
    
    zero.set_mode("cloud", provider=provider)


if __name__ == "__main__":
    main()