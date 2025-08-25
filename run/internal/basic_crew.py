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

from ai_crew import AICrewManager
from cache_manager import cache
from distributed_router import distributed_router
from agent_communication import agent_comm
from rich.console import Console

console = Console()


def get_optimal_model_and_peer(topic):
    """
    Determine the best AI model for the given task and find the optimal peer.
    
    Args:
        topic (str): The research topic
        
    Returns:
        tuple: (model_name, base_url, peer_name)
    """
    console.print("\n🤔 [bold yellow]AI models discussing best approach...[/bold yellow]")
    
    # Determine preferred model based on topic
    if any(word in topic.lower() for word in ["resume", "cv", "curriculum vitae", "job application"]):
        preferred_model = "mistral"
        console.print("💬 Analysis suggests: [bold green]mistral[/bold green] is ideal for resume optimization")
    elif "code" in topic.lower() or "programming" in topic.lower():
        preferred_model = "codellama:13b" 
        console.print("💬 Analysis suggests: [bold green]codellama:13b[/bold green] is ideal for code tasks")
    elif "technical" in topic.lower() or "analysis" in topic.lower():
        preferred_model = "llama3.1:8b"
        console.print("💬 Analysis suggests: [bold green]llama3.1:8b[/bold green] is ideal for technical analysis")
    elif "creative" in topic.lower() or "writing" in topic.lower():
        preferred_model = "gemma2"
        console.print("💬 Analysis suggests: [bold green]gemma2[/bold green] is ideal for creative content")
    else:
        preferred_model = "mistral"
        console.print("💬 Analysis suggests: [bold green]mistral[/bold green] is ideal for general research")
    
    # Fallback options if preferred model isn't available
    fallback_models = {
        "mistral": ["qwen2.5:7b", "llama3.1:8b", "llama3.2:1b"],
        "codellama:13b": ["qwen2.5:7b", "llama3.1:8b", "llama3.2:1b"],
        "llama3.1:8b": ["mistral", "qwen2.5:7b", "llama3.2:1b"],
        "gemma2": ["mistral", "llama3.1:8b", "llama3.2:1b"],
        "qwen2.5:7b": ["mistral", "llama3.1:8b", "llama3.2:1b"]
    }
    
    console.print("🔍 Searching for optimal processing peer...")
    
    # Try to find a peer with the preferred model
    base_url, peer_name = distributed_router.get_optimal_endpoint(topic, preferred_model)
    
    # If no suitable peer found for preferred model, try fallbacks
    if peer_name == "local" and preferred_model != "llama3.2:1b":
        console.print("⚠️ Preferred model not available locally, trying alternatives...")
        
        # Try each fallback model
        for fallback in fallback_models.get(preferred_model, ["llama3.2:1b"]):
            fallback_url, fallback_peer = distributed_router.get_optimal_endpoint(topic, fallback)
            if fallback_peer != "local":
                console.print(f"✅ Found peer with alternative model: [bold green]{fallback}[/bold green]")
                return fallback, fallback_url, fallback_peer
        
        # If we got here, we're using local processing with smallest model
        console.print("🔄 Using local processing with minimal model")
        return "llama3.2:1b", "http://localhost:11434", "local"
    
    console.print(f"✅ Using model: [bold blue]{preferred_model}[/bold blue] on peer: [bold blue]{peer_name}[/bold blue]\n")
    return preferred_model, base_url, peer_name


def main():
    """Run the basic crew example with AI intercommunication."""
    console.print("🤖 [bold blue]Self-Hosted Agentic AI - Basic Crew Example[/bold blue]")
    console.print("=" * 60)
    
    try:
        # Define the research topic
        topic = input("\n📝 Enter a topic to research (or press Enter for default): ").strip()
        if not topic:
            topic = "The future of artificial intelligence in healthcare"
        
        # Get optimal model and peer through AI intercommunication
        model, base_url, peer_name = get_optimal_model_and_peer(topic)
        
        # If using a GPU peer, try to use direct processing
        if "149.36.1.65" in base_url or peer_name != "local":  
            try:
                console.print(f"🚀 Attempting direct processing with peer: {peer_name}")
                start_time = time.time()
                
                # Check if we have a specific function for research tasks
                if hasattr(agent_comm, "process_research"):
                    # Use specific research processor if available
                    result = agent_comm.process_research(peer_name.split(':')[0], topic, model)
                else:
                    # Fall back to generic processing if needed
                    result = agent_comm.process_generic(peer_name.split(':')[0], 
                                                     f"Research the following topic thoroughly: {topic}", 
                                                     model)
                
                end_time = time.time()
                
                if result:
                    generation_time = end_time - start_time
                    console.print(f"⏱️  Processing time: {generation_time:.2f} seconds", style="cyan")
                    
                    # Save results to file
                    output_file = Path("output") / f"research_{topic.replace(' ', '_')[:30]}.txt"
                    output_file.parent.mkdir(exist_ok=True)
                    
                    with open(output_file, 'w', encoding='utf-8') as f:
                        f.write(f"Research Topic: {topic}\n")
                        f.write(f"AI Model Used: {model} on {peer_name}\n")
                        f.write("=" * 60 + "\n\n")
                        f.write(str(result))
                    
                    console.print("\n" + "=" * 60)
                    console.print("📊 [bold green]Research Results:[/bold green]")
                    console.print("=" * 60)
                    console.print(result)
                    console.print(f"\n💾 Results saved to: [bold blue]{output_file}[/bold blue]")
                    return
                else:
                    console.print("❌ Peer agent processing failed, falling back to crew", style="yellow")
            except Exception as e:
                console.print(f"❌ Error with peer agent: {e}", style="yellow")
                console.print("Falling back to crew-based processing...")
        
        # Initialize the AI Crew Manager with task context and best model
        console.print("🔧 Initializing AI Crew Manager...")
        manager = AICrewManager(task=topic, model=model)
        
        # Create a research crew
        console.print("👥 Creating research crew...")
        crew = manager.create_research_crew()
        
        console.print(f"\n🔍 Researching topic: [bold green]{topic}[/bold green]")
        
        # Check cache first
        cache_key = f"{topic}_{model}"
        cached_result = cache.get(cache_key, "crew_research")
        if cached_result:
            console.print("\n⚡ [bold yellow]Using cached result![/bold yellow]")
            result = cached_result
        else:
            # Execute the crew
            result = manager.execute_crew(crew, {"topic": topic, "model": model})
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
            f.write(f"AI Model Used: {model}\n")
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