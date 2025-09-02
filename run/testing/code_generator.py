import sys
import os
import time
from pathlib import Path
import litellm

# Fix: Import from the new package as suggested by the deprecation warning
from langchain_ollama import ChatOllama
from src.config import config
from rich.console import Console
from peer_discovery import PeerDiscovery
from distributed_router import DistributedRouter

console = Console()

# Instantiate the router AFTER importing PeerDiscovery and DistributedRouter
peer_discovery_instance = PeerDiscovery()
router = DistributedRouter(peer_discovery_instance)

def generate_code(prompt: str):
    max_retries = 5
    failed_peers = []

    # Initialize peer_name outside the try block
    peer_name = None

    for attempt in range(max_retries):
        try:
            ollama_url, peer_name, model_name = router.get_optimal_endpoint_and_model(prompt, failed_peers)

            # Restore printing the server and resource information here
            console.print(f"🤖 Using model: [bold green]{model_name}[/bold green] on peer: [bold cyan]{peer_name}[/bold cyan]")

            # Additional logic to print more details from the peer_discovery object
            all_peers = peer_discovery_instance.get_peers()
            peer_info = next((p for p in all_peers if p.name == peer_name), None)
            if peer_info:
                console.print(f"   Resource Details:", style="cyan")
                console.print(f"     - Memory: {peer_info.capabilities.memory:.1f} GiB", style="cyan")
                console.print(f"     - Load Avg: {peer_info.capabilities.load_avg:.1f}%", style="cyan")
                if peer_info.capabilities.gpu_available:
                    console.print(f"     - GPU Memory: {peer_info.capabilities.gpu_memory:.1f} GiB", style="cyan")


            llm = ChatOllama(
                model=model_name,
                base_url=ollama_url,
                temperature=config.model.temperature
            )

            code_prompt = f"""Generate working {prompt}.
Requirements:
- Provide ONLY the code, no explanations
- Make it functional and complete
- Use proper syntax and best practices
Code:"""

            start_time = time.time()
            # Pass the code_prompt to llm.invoke()
            result = llm.invoke(code_prompt)
            end_time = time.time()

            generation_time = end_time - start_time
            console.print(f"⏱️  Generation time: {generation_time:.2f} seconds", style="cyan")
            return result.content if result else "Generation failed."

        except (RuntimeError, litellm.APIConnectionError, ValueError, TypeError) as e:
            console.print(f"❌ LLM Call Failed (Attempt {attempt + 1}/{max_retries}): {e}", style="red")

            if peer_name:
                failed_peers.append(peer_name)
            console.print(f"🔄 Retrying with another peer or model...", style="yellow")
            continue

    console.print(f"❌ Failed to generate code after {max_retries} attempts.", style="red")
    return None


def main():
    console.print("🚀 [bold blue]ZeroAI Code Generator[/bold blue]")
    console.print("=" * 50)
    prompt = input("\n💻 What code do you want to generate? ").strip()
    if not prompt:
        prompt = "PHP class with 4 functions"
    console.print(f"\n🔧 Generating: {prompt}")
    result = generate_code(prompt)
    if result:
        console.print("\n" + "=" * 50)
        console.print("📝 [bold green]Generated Code:[/bold green]")
        console.print("=" * 50)
        print(result)
        output_file = Path("output") / f"generated_{prompt.replace(' ', '_')[:30]}.txt"
        output_file.parent.mkdir(exist_ok=True)
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write(result)
        console.print(f"\n💾 Code saved to: [bold blue]{output_file}[/bold blue]")

if __name__ == "__main__":
    main()
