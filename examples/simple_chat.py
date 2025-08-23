#!/usr/bin/env python3
"""
Simple Chat Example - Fast Local Processing
Single agent for quick responses
"""

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from ai_crew import AICrewManager
from rich.console import Console

console = Console()

def main():
    """Run simple chat example."""
    console.print("💬 [bold blue]ZeroAI Simple Chat[/bold blue]")
    console.print("=" * 40)
    
    try:
        # Initialize with faster settings
        manager = AICrewManager()
        
        while True:
            question = input("\n❓ Ask me anything (or 'quit' to exit): ").strip()
            
            if question.lower() in ['quit', 'exit', 'q']:
                break
                
            if not question:
                continue
            
            console.print(f"\n🤔 Thinking about: [green]{question}[/green]")
            
            # Direct LLM call - much faster than crew
            try:
                import requests
                response = requests.post(
                    "http://localhost:11434/api/generate",
                    json={
                        "model": "llama3.2:1b",
                        "prompt": f"Answer this question concisely: {question}",
                        "stream": False,
                        "options": {
                            "temperature": 0.3,
                            "num_predict": 200
                        }
                    },
                    timeout=30
                )
                
                if response.status_code == 200:
                    result = response.json()["response"]
                    console.print(f"\n💡 [bold green]Answer:[/bold green]\n{result}")
                else:
                    console.print("❌ Error getting response")
                    
            except Exception as e:
                console.print(f"❌ Error: {e}")
        
        console.print("\n👋 Goodbye!")
        
    except KeyboardInterrupt:
        console.print("\n👋 Goodbye!")
    except Exception as e:
        console.print(f"❌ Error: {e}")

if __name__ == "__main__":
    main()