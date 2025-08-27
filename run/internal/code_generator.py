import sys
import os
import time
from pathlib import Path
import litellm

# Fix: Import from the new package as suggested by the deprecation warning
from langchain_ollama import ChatOllama
from config import config
#from distributed_router import distributed_router
from distributed_router import DistributedRouter
from peer_discovery import PeerDiscovery  # Assuming this exists

# Initialize the router instance
peer_discovery_instance = PeerDiscovery()
router = DistributedRouter(peer_discovery_instance)



from rich.console import Console

console = Console()

def generate_code(prompt: str):
    max_retries = 5
    failed_peers = []

    for attempt in range(max_retries):
        try:
            ollama_url, peer_name, model_name = distributed_router.get_optimal_endpoint_and_model(prompt, failed_peers)
            console.print(f"🤖 Using model: [bold green]{model_name}[/bold green] on peer: [bold cyan]{peer_name}[/bold cyan]")

            # Use the new ChatOllama class
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
            result = llm.invoke(code_prompt, max_tokens=512)
            end_time = time.time()

            generation_time = end_time - start_time
            console.print(f"⏱️  Generation time: {generation_time:.2f} seconds", style="cyan")
            return result.content if result else "Generation failed."

        except (RuntimeError, litellm.APIConnectionError, ValueError) as e:
            console.print(f"❌ LLM Call Failed (Attempt {attempt + 1}/{max_retries}): {e}", style="red")

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
