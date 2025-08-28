#!/usr/bin/env python3
"""
Prime Intellect + RunPod Demo for ZeroAI

Demonstrates how to use your Prime Intellect dashboard instance
(which runs on RunPod infrastructure) with ZeroAI.
"""

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from zeroai import ZeroAI
from providers.prime_provider import PrimeIntellectProvider
from rich.console import Console
from rich.panel import Panel

console = Console()


def main():
    """Run Prime Intellect + RunPod demo."""
    
    # Welcome message
    welcome_text = """🧠 Prime Intellect + RunPod Integration

Your setup:
• Prime Intellect Dashboard: https://app.primeintellect.ai/
• RunPod Instance: refreshing-mottled-pillbug
• GPU: RTX 3070 ($0.16/hr)
• ZeroAI: Intelligent cost optimization

This demo shows how ZeroAI uses your Prime Intellect instance
for complex tasks while keeping simple tasks local (free)."""
    
    console.print(Panel(welcome_text, title="Prime Intellect + ZeroAI", border_style="blue"))
    
    # Initialize ZeroAI with Prime Intellect priority
    zero = ZeroAI(mode="smart", gpu_providers=["prime"])
    
    # Initialize Prime provider for direct control
    prime = PrimeIntellectProvider()
    
    while True:
        # Show current status
        console.print("\n📊 [bold]Current Status[/bold]")
        prime_status = prime.get_status()
        
        console.print(f"🧠 Prime Intellect: {'✅ Enabled' if prime_status['enabled'] else '❌ Disabled'}")
        console.print(f"🔗 Available: {'✅ Yes' if prime_status['available'] else '❌ No'}")
        console.print(f"🚀 Auto-start: {'✅ Yes' if prime_status['auto_start'] else '❌ No'}")
        console.print(f"💰 Cost: {prime_status.get('cost_per_hour', '$0.16/hr')}")
        
        # Show menu
        console.print("\n🎛️  [bold]Control Panel[/bold]")
        options = [
            "1. 🧪 Test Simple Task (should use local)",
            "2. 🔬 Test Complex Task (should use Prime/RunPod)",
            "3. 🚀 Start Prime Intellect Instance",
            "4. ⏹️  Stop Prime Intellect Instance", 
            "5. 📊 Check Instance Status",
            "6. ⚙️  Configure Prime Settings",
            "7. 💰 Show Cost Optimization",
            "0. 🚪 Exit"
        ]
        
        for option in options:
            console.print(option)
        
        choice = input("\n🎯 Select option (0-7): ").strip()
        
        if choice == "1":
            test_simple_task(zero)
        elif choice == "2":
            test_complex_task(zero)
        elif choice == "3":
            prime.start_session()
        elif choice == "4":
            prime.stop_session()
        elif choice == "5":
            show_detailed_status(prime)
        elif choice == "6":
            configure_prime_settings()
        elif choice == "7":
            show_cost_optimization()
        elif choice == "0":
            console.print("\n👋 Goodbye!")
            break
        else:
            console.print("❌ Invalid option", style="red")


def test_simple_task(zero: ZeroAI):
    """Test simple task (should use local)."""
    console.print("\n🧪 [bold]Testing Simple Task[/bold]")
    console.print("This should use local processing (free)")
    
    try:
        result = zero.chat("Hello, how are you today?")
        console.print("\n✅ [bold green]Result:[/bold green]")
        console.print(result[:200] + "..." if len(result) > 200 else result)
        console.print("\n💰 Cost: $0.00 (local processing)")
        
    except Exception as e:
        console.print(f"❌ Error: {e}", style="red")


def test_complex_task(zero: ZeroAI):
    """Test complex task (should use Prime/RunPod)."""
    console.print("\n🔬 [bold]Testing Complex Task[/bold]")
    console.print("This should trigger Prime Intellect (RunPod) GPU")
    
    task = """Conduct a comprehensive analysis of the cryptocurrency market 
    including technical analysis, fundamental analysis, regulatory landscape, 
    institutional adoption trends, and provide strategic investment recommendations."""
    
    try:
        console.print("🧠 Routing to Prime Intellect (RunPod)...")
        result = zero.analyze("cryptocurrency market with comprehensive strategic analysis")
        
        console.print("\n✅ [bold green]Result:[/bold green]")
        console.print(result[:300] + "..." if len(result) > 300 else result)
        console.print("\n💰 Estimated cost: ~$0.03 (2-3 minutes @ $0.16/hr)")
        
    except Exception as e:
        console.print(f"❌ Error: {e}", style="red")


def show_detailed_status(prime: PrimeIntellectProvider):
    """Show detailed Prime Intellect status."""
    console.print("\n📊 [bold]Detailed Status[/bold]")
    
    status = prime.get_status()
    
    console.print(f"🧠 Prime Intellect Status:")
    console.print(f"   Enabled: {status['enabled']}")
    console.print(f"   Available: {status['available']}")
    console.print(f"   Auto-start: {status['auto_start']}")
    console.print(f"   Session Active: {status['session_active']}")
    console.print(f"   Session ID: {status.get('session_id', 'None')}")
    
    console.print(f"\n🏃 RunPod Instance:")
    console.print(f"   Instance: refreshing-mottled-pillbug")
    console.print(f"   GPU: RTX 3070")
    console.print(f"   Cost: $0.16/hr")
    console.print(f"   Dashboard: https://app.primeintellect.ai/")


def configure_prime_settings():
    """Configure Prime Intellect settings."""
    console.print("\n⚙️  [bold]Prime Intellect Configuration[/bold]")
    
    from env_loader import ENV
    
    console.print("Current settings:")
    console.print(f"   Enabled: {ENV.get('PRIME_ENABLED', 'false')}")
    console.print(f"   Auto-start: {ENV.get('PRIME_AUTO_START', 'false')}")
    console.print(f"   Instance ID: {ENV.get('PRIME_INSTANCE_ID', 'Not set')}")
    
    if input("\nUpdate settings? (y/N): ").lower().startswith('y'):
        enabled = input("Enable Prime Intellect? (y/N): ").lower().startswith('y')
        ENV["PRIME_ENABLED"] = str(enabled).lower()
        
        if enabled:
            auto_start = input("Enable auto-start? (y/N): ").lower().startswith('y')
            ENV["PRIME_AUTO_START"] = str(auto_start).lower()
            
            instance_id = input("Instance ID (refreshing-mottled-pillbug): ").strip()
            if instance_id:
                ENV["PRIME_INSTANCE_ID"] = instance_id
        
        console.print("✅ Settings updated", style="green")


def show_cost_optimization():
    """Show cost optimization information."""
    console.print("\n💰 [bold]ZeroAI Cost Optimization[/bold]")
    
    console.print("🎯 Smart Routing Logic:")
    console.print("   • Simple tasks (complexity 1-6) → Local (FREE)")
    console.print("   • Complex tasks (complexity 7-10) → Prime/RunPod ($0.16/hr)")
    
    console.print("\n📊 Typical Costs:")
    console.print("   • Chat/Simple queries: $0.00")
    console.print("   • Research tasks: $0.01-0.05")
    console.print("   • Complex analysis: $0.05-0.15")
    console.print("   • Long documents: $0.10-0.30")
    
    console.print("\n💡 Cost Saving Tips:")
    console.print("   • Use local mode for development")
    console.print("   • Batch complex tasks together")
    console.print("   • Set higher complexity threshold (8-9)")
    console.print("   • Monitor usage in Prime dashboard")


if __name__ == "__main__":
    main()