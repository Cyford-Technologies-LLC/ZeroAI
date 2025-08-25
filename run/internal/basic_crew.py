#!/usr/bin/env python3
"""
Basic AI Crew Example

This example demonstrates how to create and run a simple AI crew
for research and content creation tasks with AI intercommunication capabilities.
"""

import sys
import os
import time
import requests
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

# Import modules from src package
from crewai import LLM, Agent, Task, Crew
from src.distributed_router import distributed_router  # Fix import path
from src.agent_communication import agent_comm  # Fix import path
from src.cache_manager import cache  # Fix import path
from rich.console import Console

console = Console()

def get_available_models():
    """Get a list of available models from Ollama."""
    try:
        response = requests.get("http://localhost:11434/api/tags")
        if response.status_code == 200:
            models = [model["name"] for model in response.json()["models"]]
            return models
        else:
            console.print("⚠️ Could not fetch available models", style="yellow")
            return []
    except Exception as e:
        console.print(f"⚠️ Error getting models: {e}", style="yellow")
        return []

def get_best_available_model(topic, available_models):
    """
    Get the best available model for the given topic.

    Args:
        topic (str): The topic to research
        available_models (list): List of available models

    Returns:
        str: Name of the best available model
    """
    console.print("\n🤔 [bold yellow]Finding optimal AI model for task...[/bold yellow]")

    # Define preferred models in order of preference for different tasks
    preferred_models = {
        "resume": ["mistral", "qwen2.5:7b", "llama3.1:8b", "llama3.2:1b"],
        "code": ["codellama:13b", "qwen2.5:7b", "llama3.1:8b", "llama3.2:1b"],
        "technical": ["llama3.1:8b", "qwen2.5:7b", "llama3.2:1b"],
        "creative": ["gemma2", "mistral", "llama3.1:8b", "llama3.2:1b"],
        "general": ["mistral", "llama3.1:8b", "qwen2.5:7b", "llama3.2:1b"]
    }

    # Determine task type based on topic
    if any(word in topic.lower() for word in ["resume", "cv", "curriculum vitae", "job application"]):
        task_type = "resume"
        reason = "professional writing capabilities and document formatting"
    elif "code" in topic.lower() or "programming" in topic.lower():
        task_type = "code"
        reason = "specialized code generation capabilities"
    elif "technical" in topic.lower() or "analysis" in topic.lower():
        task_type = "technical"
        reason = "technical analysis strengths"
    elif "creative" in topic.lower() or "writing" in topic.lower():
        task_type = "creative"
        reason = "creative content generation strengths"
    else:
        task_type = "general"
        reason = "general research capabilities"

    # Check which preferred models are available
    for model in preferred_models[task_type]:
        if model in available_models:
            console.print(f"✅ Selected model: [bold blue]{model}[/bold blue] for {reason}")
            return model

    # If none of the preferred models are available, use any available model
    if available_models:
        default_model = available_models[0]
        console.print(f"⚠️ No preferred models available. Using: [bold blue]{default_model}[/bold blue]")
        return default_model
    else:
        # If no models are available, use a guaranteed default that should come with Ollama
        console.print("⚠️ No models found. Using default model: [bold blue]llama3.2:1b[/bold blue]")
        return "llama3.2:1b"


def main():
    console.print("🚀 [bold blue]ZeroAI Basic Crew Example[/bold blue]")
    console.print("=" * 50)

    # Get the research topic
    topic = input("\n📝 Enter a topic to research (or press Enter for default): ").strip()
    if not topic:
        topic = "The future of artificial intelligence in healthcare"

    # Get list of available models
    available_models = get_available_models()
    console.print(f"📋 Available models: {', '.join(available_models) if available_models else 'None detected'}")

    # Get best available model for the task
    model_name = get_best_available_model(topic, available_models)
    console.print(f"🧠 Using model: {model_name}")

    # Use distributed routing to find optimal processing peer
    try:
        base_url, peer_name = distributed_router.get_optimal_endpoint(topic, model_name)
        if peer_name == "local":
            console.print("💻 Using local processing (no suitable peers)")
        console.print(f"🌐 Processing with: {peer_name}")
    except Exception as e:
        console.print(f"⚠️ Error with distributed router: {e}", style="yellow")
        base_url = "http://localhost:11434"
        peer_name = "local"
        console.print("💻 Falling back to local processing", style="yellow")

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
    try:
        cache_key = f"{topic}_{model_name}"
        cached_result = cache.get(cache_key, "research")

        if cached_result:
            console.print("⚡ [bold yellow]Using cached result![/bold yellow]")
            result = cached_result
    except Exception as e:
        console.print(f"⚠️ Cache error: {e}", style="yellow")
        cached_result = None

    if not cached_result:
        # Set up local processing with CrewAI
        try:
            # Make sure we're using a model that exists
            if model_name not in available_models and peer_name == "local":
                # If model doesn't exist locally and we're not using a peer, switch to a guaranteed model
                if "llama3.2:1b" in available_models:
                    local_model = "llama3.2:1b"
                elif available_models:
                    local_model = available_models[0]  # Use any available model
                else:
                    # If no models are found, suggest pulling one
                    console.print("❌ No models available. Please run 'ollama pull llama3.2:1b'", style="red")
                    return

                console.print(f"⚠️ Model {model_name} not available, using {local_model} instead", style="yellow")
                model_name = local_model

            llm = LLM(
                model=f"ollama/{model_name}",
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

            # Create research task - Adding expected_output to fix the validation error
            research_task = Task(
                description=f"Research the following topic thoroughly: {topic}",
                expected_output="Detailed research findings on the topic",
                agent=researcher
            )

            # Create writing task - Adding expected_output to fix the validation error
            writing_task = Task(
                description=f"Create a comprehensive report on {topic} based on the research",
                expected_output="A well-structured, comprehensive report",
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
            try:
                cache.set(cache_key, "research", result)
            except Exception as e:
                console.print(f"⚠️ Cache error during save: {e}", style="yellow")
        except Exception as e:
            console.print(f"❌ Error during processing: {e}", style="red")

            if "model 'mistral' not found" in str(e):
                console.print("💡 The selected model is not installed. Run: `ollama pull llama3.2:1b`", style="yellow")
            else:
                console.print("💡 Make sure Ollama is running: `ollama serve`", style="yellow")
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