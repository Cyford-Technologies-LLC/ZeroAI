#!/usr/bin/env python3
"""
Basic AI Crew Example

This example demonstrates how to create and run a simple AI crew
for research and content creation tasks with AI intercommunication capabilities.
"""

import sys
import os
import time
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

from crewai import LLM, Agent, Task, Crew
from distributed_router import distributed_router
from agent_communication import agent_comm
from cache_manager import cache
from rich.console import Console

console = Console()

def get_best_model_for_task(topic):
    """
    Use AI intercommunication to determine the best model for the task.

    Args:
        topic (str): The research topic

    Returns:
        str: The best model name for the task
    """
    console.print("\n🤔 [bold yellow]Finding optimal AI model for task...[/bold yellow]")

    # Analyze topic to determine best model
    if any(word in topic.lower() for word in ["resume", "cv", "curriculum vitae", "job application"]):
        best_model = "mistral"
        reason = "professional writing capabilities and document formatting"
    elif "code" in topic.lower() or "programming" in topic.lower():
        best_model = "codellama:13b"
        reason = "specialized code generation capabilities"
    elif "technical" in topic.lower() or "analysis" in topic.lower():
        best_model = "llama3.1:8b"
        reason = "technical analysis strengths"
    elif "creative" in topic.lower() or "writing" in topic.lower():
        best_model = "gemma2"
        reason = "creative content generation strengths"
    else:
        best_model = "mistral"
        reason = "general research capabilities"

    console.print(f"✅ Selected model: [bold blue]{best_model}[/bold blue] for {reason}")
    return best_model


def main():
    console.print("🚀 [bold blue]ZeroAI Basic Crew Example[/bold blue]")
    console.print("=" * 50)

    # Get the research topic
    topic = input("\n📝 Enter a topic to research (or press Enter for default): ").strip()
    if not topic:
        topic = "The future of artificial intelligence in healthcare"

    # Get best model through AI intercommunication
    model_name = get_best_model_for_task(topic)
    console.print(f"🧠 Using model: {model_name}")

    # Use distributed routing to find optimal processing peer
    base_url, peer_name = distributed_router.get_optimal_endpoint(topic, model_name)
    console.print(f"🌐 Processing with: {peer_name}")

    # Try using peer agent directly for better performance if available
    if peer_name != "local":
        console.print("🔄 Attempting direct processing with peer agent...")
        try:
            start_time = time.time()
            result = agent_comm.process_research_task(peer_name.split(':')[0], topic, model_name)
            end_time = time.time()

            if result:
                generation_time = end_time - start_time
                console.print(f"⏱️  Generation time: {generation_time:.2f} seconds", style="cyan")

                # Display and save results
                console.print("\n" + "=" * 50)
                console.print("📊 [bold green]Research Results:[/bold green]")
                console.print("=" * 50)
                print(result)

                # Save to file
                output_file = Path("output") / f"research_{topic.replace(' ', '_')[:30]}.txt"
                output_file.parent.mkdir(exist_ok=True)

                with open(output_file, 'w', encoding='utf-8') as f:
                    f.write(result)

                console.print(f"\n💾 Research saved to: [bold blue]{output_file}[/bold blue]")
                return
            else:
                console.print("❌ Peer agent processing failed", style="red")
        except Exception as e:
            console.print(f"❌ Error with peer agent: {e}", style="red")

    # Fallback to local crew-based processing
    console.print("🔄 Falling back to local crew-based processing", style="yellow")

    # Check cache first
    cache_key = f"{topic}_{model_name}"
    cached_result = cache.get(cache_key, "research")

    if cached_result:
        console.print("⚡ [bold yellow]Using cached result![/bold yellow]")
        result = cached_result
    else:
        # Set up local processing with CrewAI
        try:
            # Create LLM instance with the appropriate model
            if peer_name == "local":
                # For local processing, use a smaller model if the preferred is too large
                if model_name in ["codellama:13b", "llama3.1:70b"]:
                    local_model = "llama3.2:1b"
                    console.print(f"⚠️ Model {model_name} too large for local, using {local_model}")
                else:
                    local_model = model_name
            else:
                local_model = model_name

            llm = LLM(
                model=f"ollama/{local_model}",
                base_url=base_url,
                temperature=0.7,
                max_tokens=512
            )

            # Create the researcher agent
            researcher = Agent(
                role="Researcher",
                goal=f"Research {topic} thoroughly and provide comprehensive information",
                backstory="You are an expert researcher with access to vast knowledge",
                llm=llm
            )

            # Create the writer agent
            writer = Agent(
                role="Writer",
                goal="Create well-structured, informative content based on research",
                backstory="You are a skilled writer who can communicate complex ideas clearly",
                llm=llm
            )

            # Create research task
            research_task = Task(
                description=f"Research the following topic thoroughly: {topic}",
                agent=researcher
            )

            # Create writing task
            writing_task = Task(
                description=f"Create a comprehensive report on {topic} based on the research",
                agent=writer,
                dependencies=[research_task]
            )

            # Create and run the crew
            crew = Crew(
                agents=[researcher, writer],
                tasks=[research_task, writing_task],
                verbose=True
            )

            console.print("\n🔍 Crew starting research and content creation...")
            start_time = time.time()
            result = crew.kickoff()
            end_time = time.time()

            generation_time = end_time - start_time
            console.print(f"⏱️  Generation time: {generation_time:.2f} seconds", style="cyan")

            # Cache the result
            cache.set(cache_key, "research", result)
        except Exception as e:
            console.print(f"❌ Error during processing: {e}", style="red")
            console.print("💡 Make sure Ollama is running: `ollama serve`")
            return

    # Display and save results
    console.print("\n" + "=" * 50)
    console.print("📊 [bold green]Research Results:[/bold green]")
    console.print("=" * 50)
    print(result)

    # Save to file
    output_file = Path("output") / f"research_{topic.replace(' ', '_')[:30]}.txt"
    output_file.parent.mkdir(exist_ok=True)

    with open(output_file, 'w', encoding='utf-8') as f:
        f.write(result)

    console.print(f"\n💾 Research saved to: [bold blue]{output_file}[/bold blue]")

if __name__ == "__main__":
    main()