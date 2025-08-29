#!/usr/bin/env python3
"""
Code Generator Example

Direct code generation without research crew overhead.
"""

import sys
import os
import time
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

# Fix: Import Ollama chat model directly from langchain
from langchain_community.chat_models import ChatOllama
from config import config
from distributed_router import distributed_router
from rich.console import Console

console = Console()

def generate_code(prompt: str):
    """Generate code directly using the optimal model."""

    try:
        ollama_url, peer_name, model_name = distributed_router.get_optimal_endpoint_and_model(prompt)
    except Exception as e:
        console.print(f"❌ Router failed to find a suitable LLM provider: {e}", style="red")
        return None

    console.print(f"🤖 Using model: [bold green]{model_name}[/bold green] on peer: [bold cyan]{peer_name}[/bold cyan]")

    try:
        # Fix: Instantiate ChatOllama directly for a single LLM call
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
        # Fix: Use the standard invoke() method for direct calls
        result = llm.invoke(code_prompt, max_tokens=512)
        end_time = time.time()

        generation_time = end_time - start_time
        console.print(f"⏱️  Generation time: {generation_time:.2f} seconds", style="cyan")

        return result.content if result else "Generation failed."

    except Exception as e:
        console.print(f"❌ LLM processing failed: {e}", style="red")
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
