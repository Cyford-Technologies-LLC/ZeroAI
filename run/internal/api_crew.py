#!/usr/bin/env python3
"""
API Crew for Customer Service

This crew demonstrates a customer service agent that can handle inquiries and,
if necessary, delegate complex issues to specialized crews.
"""
try:
    __import__('pysqlite3')
    import sys
    sys.modules['sqlite3'] = sys.modules.pop('pysqlite3')
except ImportError:
    pass

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

from ai_crew import AICrewManager
from cache_manager import cache
from distributed_router import distributed_router
from rich.console import Console

# Import necessary CrewAI components for creating agents and tasks
from crewai import Crew

console = Console()

def main():
    """Run the customer service crew example."""
    console.print("🤖 [bold blue]Self-Hosted Agentic AI - Customer Service Crew[/bold blue]")
    console.print("=" * 60)

    try:
        # Define the customer inquiry
        topic = input("\n📝 Enter your customer inquiry: ").strip()
        if not topic:
            topic = "I have a question about my last payment and want to know my account balance."

        # Initialize the AI Crew Manager with category and inputs
        console.print("🔧 Initializing AI Crew Manager...")
        manager = AICrewManager(distributed_router, category="customer_service", inputs={"topic": topic})

        # Create the customer service crew via the unified method
        console.print("👥 Creating customer service crew...")
        crew = manager.create_crew_for_category({"topic": topic})

        console.print(f"\n🔍 Processing inquiry: [bold green]{topic}[/bold green]")

        # Execute the crew
        result = manager.execute_crew(crew, {"topic": topic})

        # Display results
        console.print("\n" + "=" * 60)
        console.print("📊 [bold green]Inquiry Results:[/bold green]")
        console.print("=" * 60)
        console.print(result)

        # Save results to file
        output_file = Path("output") / f"customer_service_{topic.replace(' ', '_')[:30]}.txt"
        output_file.parent.mkdir(exist_ok=True)

        with open(output_file, 'w', encoding='utf-8') as f:
            f.write(f"Customer Inquiry: {topic}\n")
            f.write("=" * 60 + "\n\n")
            f.write(str(result))

        console.print(f"\n💾 Results saved to: [bold blue]{output_file}[/bold blue]")

    except KeyboardInterrupt:
        console.print("\n⚠️  Operation cancelled by user.")
    except Exception as e:
        console.print(f"\n❌ Error: {e}")
        console.print("💡 Make sure Ollama is running: `ollama serve`")


if __name__ == "__main__":
    main()
