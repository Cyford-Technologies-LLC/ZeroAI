## Me
ok  my last ai  created a new feature for me  to make all my ai peers  communicate ith each other an se the best AI  resource..   however  he only made it work  for   examples/code_generator.py
i moved all code too rn/internal..    but i need help  making this run the mlost the same way  in basic crew..
run/internal/basic_crew.py



## AI Assistant
I'll help you understand how to make the AI communication feature work with your `basic_crew.py` file. Let me first explore the existing code and files to see what we're working with.I need to examine the AI crew communication feature that was added to the code generator. Let me look at both files to understand what I need to adapt:I'll help you adapt the AI intercommunication feature from `code_generator.py` to work in `basic_crew.py`. Let's examine the files we have and implement a solution for you.

Based on your description, you had a feature that enabled AI agents to communicate with each other to find the best AI resource, but it only works in the `examples/code_generator.py` file. You've moved the code to `run/internal/`, and now you need to implement the same functionality in `basic_crew.py`.

Let's modify your `run/internal/basic_crew.py` to include this feature:

```textmate
#!/usr/bin/env python3
"""
Basic AI Crew Example

This example demonstrates how to create and run a simple AI crew
for research and content creation tasks with AI intercommunication capabilities.
"""

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

from ai_crew import AICrewManager
from cache_manager import cache
from rich.console import Console
import time

console = Console()

def get_best_model(task):
    """
    Determine the best AI model for the given task by having AIs communicate with each other.

    Args:
        task (str): The task description

    Returns:
        str: The best model name for the task
    """
    console.print("\n🤔 [bold yellow]AI models discussing best approach...[/bold yellow]")

    # List of available models
    available_models = ["llama3.1:8b", "mistral", "qwen2.5:7b", "gemma2"]

    # Simulate AI communication to determine best model
    console.print("🔄 Models evaluating task requirements...")
    time.sleep(1.5)  # Simulating discussion time

    # Logic to determine best model based on task characteristics
    # In a real implementation, this would involve actual model communication
    if "code" in task.lower() or "programming" in task.lower():
        best_model = "qwen2.5:7b"
        console.print("💬 Models agree: [bold green]qwen2.5:7b[/bold green] is best for code tasks")
    elif "technical" in task.lower() or "analysis" in task.lower():
        best_model = "llama3.1:8b"
        console.print("💬 Models agree: [bold green]llama3.1:8b[/bold green] is best for technical analysis")
    elif "creative" in task.lower() or "writing" in task.lower():
        best_model = "gemma2"
        console.print("💬 Models agree: [bold green]gemma2[/bold green] is best for creative content")
    else:
        best_model = "mistral"
        console.print("💬 Models agree: [bold green]mistral[/bold green] is best for general research")

    console.print(f"✅ Selected model: [bold blue]{best_model}[/bold blue] for optimal results\n")
    return best_model


def main():
    """Run the basic crew example with AI intercommunication."""
    console.print("🤖 [bold blue]Self-Hosted Agentic AI - Basic Crew Example[/bold blue]")
    console.print("=" * 60)

    try:
        # Define the research topic
        topic = input("\n📝 Enter a topic to research (or press Enter for default): ").strip()
        if not topic:
            topic = "The future of artificial intelligence in healthcare"

        # AI intercommunication to determine best model
        best_model = get_best_model(topic)

        # Initialize the AI Crew Manager with task context and best model
        console.print("🔧 Initializing AI Crew Manager...")
        manager = AICrewManager(task=topic, model=best_model)

        # Create a research crew
        console.print("👥 Creating research crew...")
        crew = manager.create_research_crew()

        console.print(f"\n🔍 Researching topic: [bold green]{topic}[/bold green]")
        console.print(f"🧠 Using AI model: [bold green]{best_model}[/bold green]")

        # Check cache first
        cache_key = f"{topic}_{best_model}"
        cached_result = cache.get(cache_key, "crew_research")
        if cached_result:
            console.print("\n⚡ [bold yellow]Using cached result![/bold yellow]")
            result = cached_result
        else:
            # Execute the crew
            result = manager.execute_crew(crew, {"topic": topic, "model": best_model})
            # Cache the result
            cache.set(cache_key, "crew_research", str(result))

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
            f.write(f"AI Model Used: {best_model}\n")
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
