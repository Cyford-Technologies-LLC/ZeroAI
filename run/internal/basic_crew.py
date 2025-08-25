#!/usr/bin/env python3
"""
Basic AI Crew Example

This example demonstrates how to create and run a simple AI crew
for research and content creation tasks.
"""

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

from ai_crew import AICrewManager
from cache_manager import cache

from rich.console import Console

console = Console()


def main():
    """Run the basic crew example."""
    console.print("🤖 [bold blue]Self-Hosted Agentic AI - Basic Crew Example[/bold blue]")
    console.print("=" * 60)

    try:
        # Define the research topic
        topic = input("\n📝 Enter a topic to research (or press Enter for default): ").strip()
        if not topic:
            topic = "The future of artificial intelligence in healthcare"

        # Initialize the AI Crew Manager with task context
        console.print("🔧 Initializing AI Crew Manager...")
        manager = AICrewManager(task=topic)

        # Create a research crew
        console.print("👥 Creating research crew...")
        crew = manager.create_research_crew()

        console.print(f"\n🔍 Researching topic: [bold green]{topic}[/bold green]")

        # Check cache first
        cached_result = cache.get(topic, "crew_research")
        if cached_result:
            console.print("\n⚡ [bold yellow]Using cached result![/bold yellow]")
            result = cached_result
        else:
            # Execute the crew
            result = manager.execute_crew(crew, {"topic": topic})
            # Cache the result
            cache.set(topic, "crew_research", str(result))

        # Display results
        console.print("\n" + "=" * 60)
        console.print("📊 [bold green]Research Results:[/bold green]")
        console.print("=" * 60)
        console.print(result)

        # Save results to file
        output_file = Path("output") / f"research_{topic.replace(' ', '_')[:30]}.txt"
        output_file.parent.mkdir(exist_ok=True)

        with open(output_file, 'w', encoding='utf-8') as f:
            f.write(f"Research Topic: {topic}\n")
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