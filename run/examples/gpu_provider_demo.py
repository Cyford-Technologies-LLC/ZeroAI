#!/usr/bin/env python3
"""
GPU Provider Demo

Demonstrates intelligent GPU provider selection between Thunder Compute
and Prime Intellect with automatic failover and priority management.
"""

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from smart_ai_manager import SmartAIManager
from providers.gpu_manager import GPUProviderManager
from rich.console import Console
from rich.table import Table
from rich.panel import Panel

console = Console()


def main():
    """Run GPU provider demo."""
    
    # Welcome message
    welcome_text = """🎮 Multi-GPU Provider Demo

This demo shows intelligent GPU provider selection:
• Automatic provider detection and availability checking
• Priority-based selection (Thunder → Prime → Local)
• Seamless failover between providers
• Cost optimization through smart routing"""
    
    console.print(Panel(welcome_text, title="GPU Provider Demo", border_style="blue"))
    
    # Initialize managers
    ai = SmartAIManager()
    gpu_manager = GPUProviderManager()
    
    while True:
        # Show current status
        gpu_manager.show_status()
        
        # Show menu
        console.print("\n🎛️  [bold]GPU Control Panel[/bold]")
        table = Table()
        table.add_column("Option", style="cyan")
        table.add_column("Description", style="white")
        
        table.add_row("1", "🧪 Test Simple Task (should use local)")
        table.add_row("2", "🔬 Test Complex Task (should use GPU)")
        table.add_row("3", "⚡ Enable GPU Access")
        table.add_row("4", "❌ Disable GPU Access")
        table.add_row("5", "🎯 Set Provider Priority")
        table.add_row("6", "🔧 Configure Thunder Compute")
        table.add_row("7", "🧠 Configure Prime Intellect")
        table.add_row("8", "📊 Show Detailed Status")
        table.add_row("9", "🧹 Cleanup Resources")
        table.add_row("0", "🚪 Exit")
        
        console.print(table)
        
        choice = input("\n🎯 Select option (0-9): ").strip()
        
        if choice == "1":
            test_simple_task(ai)
        elif choice == "2":
            test_complex_task(ai)
        elif choice == "3":
            gpu_manager.enable_gpu_access()
        elif choice == "4":
            gpu_manager.disable_gpu_access()
        elif choice == "5":
            set_provider_priority(gpu_manager)
        elif choice == "6":
            configure_thunder(gpu_manager)
        elif choice == "7":
            configure_prime(gpu_manager)
        elif choice == "8":
            show_detailed_status(gpu_manager)
        elif choice == "9":
            gpu_manager.cleanup_resources()
        elif choice == "0":
            gpu_manager.cleanup_resources()
            console.print("\n👋 Goodbye!")
            break
        else:
            console.print("❌ Invalid option", style="red")


def test_simple_task(ai: SmartAIManager):
    """Test a simple task that should use local processing."""
    console.print("\n🧪 [bold]Testing Simple Task[/bold]")
    
    task = "Hello, what's the weather like?"
    console.print(f"📝 Task: {task}")
    
    try:
        result = ai.process_task_with_smart_routing(
            task_description=task,
            inputs={"query": task}
        )
        
        console.print("\n✅ [bold green]Result:[/bold green]")
        console.print(result[:200] + "..." if len(result) > 200 else result)
        
    except Exception as e:
        console.print(f"❌ Error: {e}", style="red")


def test_complex_task(ai: SmartAIManager):
    """Test a complex task that should trigger GPU providers."""
    console.print("\n🔬 [bold]Testing Complex Task[/bold]")
    
    task = """Conduct a comprehensive multi-dimensional analysis of the global 
    cryptocurrency market including detailed technical analysis, fundamental 
    analysis, regulatory landscape assessment, institutional adoption trends, 
    macroeconomic factors, and provide strategic investment recommendations 
    with risk assessment for the next 12 months."""
    
    console.print(f"📝 Task: {task[:100]}...")
    
    try:
        result = ai.process_task_with_smart_routing(
            task_description=task,
            inputs={"topic": "cryptocurrency market analysis"}
        )
        
        console.print("\n✅ [bold green]Result:[/bold green]")
        console.print(result[:300] + "..." if len(result) > 300 else result)
        
    except Exception as e:
        console.print(f"❌ Error: {e}", style="red")


def set_provider_priority(gpu_manager: GPUProviderManager):
    """Set GPU provider priority order."""
    console.print("\n🎯 [bold]Set Provider Priority[/bold]")
    console.print("Available providers: thunder, prime")
    console.print("Current priority:", ", ".join(gpu_manager.get_provider_priority()))
    
    priority_input = input("\n🎯 Enter new priority (comma-separated, e.g., 'prime,thunder'): ").strip()
    
    if priority_input:
        priority_list = [p.strip() for p in priority_input.split(",")]
        gpu_manager.set_provider_priority(priority_list)
    else:
        console.print("❌ No changes made", style="yellow")


def configure_thunder(gpu_manager: GPUProviderManager):
    """Configure Thunder Compute settings."""
    console.print("\n⚡ [bold]Thunder Compute Configuration[/bold]")
    
    from env_loader import ENV
    
    enabled = input("Enable Thunder Compute? (y/N): ").lower().startswith('y')
    ENV["THUNDER_ENABLED"] = str(enabled).lower()
    
    if enabled:
        auto_start = input("Enable auto-start? (y/N): ").lower().startswith('y')
        ENV["THUNDER_AUTO_START"] = str(auto_start).lower()
        
        api_key = input("Thunder API Key (leave blank to keep current): ").strip()
        if api_key:
            ENV["THUNDER_API_KEY"] = api_key
        
        instance_id = input("Thunder Instance ID (leave blank to keep current): ").strip()
        if instance_id:
            ENV["THUNDER_INSTANCE_ID"] = instance_id
    
    console.print("✅ Thunder Compute configuration updated", style="green")


def configure_prime(gpu_manager: GPUProviderManager):
    """Configure Prime Intellect settings."""
    console.print("\n🧠 [bold]Prime Intellect Configuration[/bold]")
    
    from env_loader import ENV
    
    enabled = input("Enable Prime Intellect? (y/N): ").lower().startswith('y')
    ENV["PRIME_ENABLED"] = str(enabled).lower()
    
    if enabled:
        auto_start = input("Enable auto-start? (y/N): ").lower().startswith('y')
        ENV["PRIME_AUTO_START"] = str(auto_start).lower()
        
        api_key = input("Prime API Key (leave blank to keep current): ").strip()
        if api_key:
            ENV["PRIME_API_KEY"] = api_key
        
        endpoint = input("Prime Endpoint (leave blank for default): ").strip()
        if endpoint:
            ENV["PRIME_ENDPOINT"] = endpoint
    
    console.print("✅ Prime Intellect configuration updated", style="green")


def show_detailed_status(gpu_manager: GPUProviderManager):
    """Show detailed status of all providers."""
    console.print("\n📊 [bold]Detailed Provider Status[/bold]")
    
    status = gpu_manager.get_all_status()
    
    # GPU Access Status
    console.print(f"\n🎮 GPU Access: {'✅ Enabled' if status['gpu_access_enabled'] else '❌ Disabled'}")
    console.print(f"🎯 Priority: {', '.join(status['provider_priority'])}")
    console.print(f"🟢 Available: {', '.join(status['available_providers']) or 'None'}")
    console.print(f"🔥 Active: {status['active_provider'] or 'None'}")
    
    # Thunder Status
    thunder = status['thunder']
    console.print(f"\n⚡ [bold]Thunder Compute:[/bold]")
    console.print(f"   Enabled: {thunder['enabled']}")
    console.print(f"   Available: {thunder.get('available', 'Unknown')}")
    console.print(f"   Auto-start: {thunder.get('auto_start', 'Unknown')}")
    
    # Prime Status
    prime = status['prime']
    console.print(f"\n🧠 [bold]Prime Intellect:[/bold]")
    console.print(f"   Enabled: {prime['enabled']}")
    console.print(f"   Available: {prime['available']}")
    console.print(f"   Auto-start: {prime['auto_start']}")
    console.print(f"   Session Active: {prime['session_active']}")


if __name__ == "__main__":
    main()