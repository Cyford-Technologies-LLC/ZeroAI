#!/usr/bin/env python3
"""
Example: Controlled AI Crew Execution
Shows how to run crews with graceful shutdown and real-time interaction.
"""

import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from src.ai_dev_ops_crew import AIOpsCrewManager
from distributed_router import DistributedRouter
from rich.console import Console

console = Console()

def main():
    """Run AI crew with controlled execution."""
    
    # Initialize router and manager
    router = DistributedRouter()
    
    inputs = {
        "prompt": "Analyze the ZeroAI codebase and suggest improvements",
        "project_id": "zeroai",
        "category": "code_analysis",
        "repository": "https://github.com/Cyford-Technologies-LLC/ZeroAI.git"
    }
    
    manager = AIOpsCrewManager(router, "zeroai", inputs)
    
    console.print("🚀 [bold green]Starting controlled AI crew execution[/bold green]")
    console.print("📋 Task: Code analysis and improvement suggestions")
    console.print("🎮 Interactive controls available during execution\n")
    
    try:
        # This would normally call crew.kickoff(), but now with control
        console.print("⚠️ [yellow]This is a demo - replace with actual crew execution[/yellow]")
        
        # Example of how to use controlled execution:
        # result = manager.execute_with_control(crew, inputs)
        
        console.print("✅ Demo completed!")
        
    except Exception as e:
        console.print(f"❌ Error: {e}")
        return 1
        
    return 0

if __name__ == "__main__":
    exit(main())