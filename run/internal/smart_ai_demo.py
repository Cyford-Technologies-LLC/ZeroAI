#!/usr/bin/env python3
"""
Smart AI Demo with Thunder Compute Integration

Demonstrates intelligent task routing based on complexity,
with easy Thunder Compute enable/disable controls.
"""

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

from smart_ai_manager import SmartAIManager
from rich.console import Console
from rich.table import Table
from rich.panel import Panel

console = Console()


def main():
    """Run smart AI demo with Thunder integration."""
    
    # Welcome message
    welcome_text = """🧠 Smart AI with Thunder Compute Integration

This demo shows how your AI intelligently routes tasks:
• Simple tasks → Local processing (free, slower)
• Complex tasks → Thunder Compute GPU (paid, fast)
• Full control over when to spend money"""
    
    console.print(Panel(welcome_text, title="Smart AI Demo", border_style="blue"))
    
    # Initialize smart AI manager
    ai = SmartAIManager()
    
    while True:
        # Show current status
        ai.show_status()
        
        # Show menu
        console.print("\n🎛️  [bold]Control Panel[/bold]")
        table = Table()
        table.add_column("Option", style="cyan")
        table.add_column("Description", style="white")
        
        table.add_row("1", "🧪 Test Simple Task (local)")
        table.add_row("2", "🔬 Test Complex Task (smart routing)")
        table.add_row("3", "⚡ Enable Thunder Mode")
        table.add_row("4", "❌ Disable Thunder Mode")
        table.add_row("5", "🏠 Enable Local-Only Mode (Budget)")
        table.add_row("6", "🌐 Disable Local-Only Mode")
        table.add_row("7", "🎯 Set Complexity Threshold")
        table.add_row("8", "📊 Show Status")
        table.add_row("9", "🚪 Exit")
        
        console.print(table)
        
        choice = input("\n🎯 Select option (1-9): ").strip()
        
        if choice == "1":
            test_simple_task(ai)
        elif choice == "2":
            test_complex_task(ai)
        elif choice == "3":
            enable_thunder_mode(ai)
        elif choice == "4":
            ai.disable_thunder_mode()
        elif choice == "5":
            ai.enable_local_only_mode()
        elif choice == "6":
            ai.disable_local_only_mode()
        elif choice == "7":
            set_complexity_threshold(ai)
        elif choice == "8":
            ai.show_status()
        elif choice == "9":
            console.print("\n👋 Goodbye!")
            break
        else:
            console.print("❌ Invalid option", style="red")


def test_simple_task(ai: SmartAIManager):
    """Test a simple task that should use local processing."""
    console.print("\n🧪 [bold]Testing Simple Task[/bold]")
    
    task = "Hello, how are you today?"
    console.print(f"📝 Task: {task}")
    
    try:
        result = ai.process_task_with_smart_routing(
            task_description=task,
            inputs={"query": task}
        )
        
        console.print("\n✅ [bold green]Result:[/bold green]")
        console.print(result)
        
    except Exception as e:
        console.print(f"❌ Error: {e}", style="red")


def test_complex_task(ai: SmartAIManager):
    """Test a complex task that should trigger Thunder Compute."""
    console.print("\n🔬 [bold]Testing Complex Task[/bold]")
    
    task = """Conduct a comprehensive analysis of the renewable energy market, 
    including detailed market trends, competitive landscape, technological 
    innovations, regulatory impacts, and strategic recommendations for the 
    next 5 years. Provide actionable insights for investment decisions."""
    
    console.print(f"📝 Task: {task[:100]}...")
    
    try:
        result = ai.process_task_with_smart_routing(
            task_description=task,
            inputs={"topic": "renewable energy market analysis"}
        )
        
        console.print("\n✅ [bold green]Result:[/bold green]")
        console.print(result[:500] + "..." if len(result) > 500 else result)
        
    except Exception as e:
        console.print(f"❌ Error: {e}", style="red")


def enable_thunder_mode(ai: SmartAIManager):
    """Configure Thunder Compute settings."""
    console.print("\n⚡ [bold]Thunder Compute Configuration[/bold]")
    
    auto_start = input("🚀 Enable auto-start for complex tasks? (y/N): ").lower().startswith('y')
    
    if auto_start:
        try:
            threshold = int(input("🎯 Complexity threshold (1-10, default 7): ") or "7")
            if not 1 <= threshold <= 10:
                threshold = 7
        except ValueError:
            threshold = 7
    else:
        threshold = 10  # Never auto-start
    
    ai.enable_thunder_mode(auto_start, threshold)


def set_complexity_threshold(ai: SmartAIManager):
    """Set complexity threshold for Thunder auto-start."""
    console.print("\n🎯 [bold]Set Complexity Threshold[/bold]")
    console.print("1-3: Only very simple tasks stay local")
    console.print("4-6: Balanced approach")
    console.print("7-9: Most tasks stay local (recommended)")
    console.print("10: Never auto-start Thunder")
    
    try:
        threshold = int(input("\n🎯 Enter threshold (1-10): "))
        ai.set_complexity_threshold(threshold)
    except ValueError:
        console.print("❌ Invalid number", style="red")


if __name__ == "__main__":
    main()