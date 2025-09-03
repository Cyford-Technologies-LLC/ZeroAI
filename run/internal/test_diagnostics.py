#!/usr/bin/env python3
# run/internal/test_diagnostics.py
"""
Test script for the Diagnostic Agent (Dr. Watson)
"""

import sys
import os
from pathlib import Path

# Set CREW_TYPE for internal operations
os.environ["CREW_TYPE"] = "internal"

# Add project root to Python path
project_root = Path(__file__).parent.parent.parent
sys.path.insert(0, str(project_root))

from rich.console import Console
from src.crews.internal.diagnostics import create_diagnostic_agent, create_diagnostics_task
from src.distributed_router import DistributedRouter
from src.peer_discovery import PeerDiscovery
from crewai import Crew, Process

console = Console()

def test_diagnostic_agent():
    """Test if Dr. Watson (Diagnostic Agent) can start up properly."""
    try:
        console.print("🔬 Testing Diagnostic Agent (Dr. Watson)...", style="blue")
        
        # Initialize router
        discovery = PeerDiscovery()
        router = DistributedRouter(discovery)
        
        # Test inputs
        inputs = {
            "log_output": "Test log output for analysis",
            "error_context": "Testing diagnostic agent startup",
            "verbose": True
        }
        
        # Create diagnostic agent
        console.print("Creating Dr. Watson...", style="cyan")
        diagnostic_agent = create_diagnostic_agent(router, inputs)
        
        console.print(f"✅ Dr. Watson created successfully!", style="green")
        console.print(f"   Role: {diagnostic_agent.role}", style="dim")
        console.print(f"   Name: {diagnostic_agent.name}", style="dim")
        console.print(f"   Tools: {len(diagnostic_agent.tools)} tools available", style="dim")
        
        # Create diagnostic task
        console.print("Creating diagnostic task...", style="cyan")
        diagnostic_task = create_diagnostics_task(diagnostic_agent, inputs)
        
        console.print(f"✅ Diagnostic task created successfully!", style="green")
        console.print(f"   Description: {diagnostic_task.description[:100]}...", style="dim")
        
        # Test crew creation
        console.print("Creating diagnostic crew...", style="cyan")
        crew = Crew(
            agents=[diagnostic_agent],
            tasks=[diagnostic_task],
            process=Process.sequential,
            verbose=True
        )
        
        console.print(f"✅ Diagnostic crew created successfully!", style="green")
        console.print(f"🔬 Dr. Watson is ready to help with internal issues!", style="bold green")
        
        return True
        
    except Exception as e:
        console.print(f"❌ Error testing diagnostic agent: {e}", style="red")
        import traceback
        console.print(traceback.format_exc(), style="red")
        return False

if __name__ == "__main__":
    success = test_diagnostic_agent()
    if success:
        console.print("\n🎉 Dr. Watson diagnostic test passed!", style="bold green")
    else:
        console.print("\n💥 Dr. Watson diagnostic test failed!", style="bold red")
        sys.exit(1)