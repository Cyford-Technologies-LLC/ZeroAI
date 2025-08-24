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

from crewai import LLM
from config import config
from distributed_router import distributed_router
from rich.console import Console

console = Console()

def generate_code(prompt: str):
    """Generate code directly using the optimal model."""
    
    # Get optimal model for coding task
    coding_keywords = ['code', 'php', 'python', 'javascript', 'html', 'css', 'sql']
    if any(keyword in prompt.lower() for keyword in coding_keywords):
        model_name = "codellama:13b"
    else:
        model_name = "llama3.1:8b"
    
    console.print(f"🤖 Using model: {model_name}")
    
    # Get optimal endpoint from distributed router
    base_url, peer_name = distributed_router.get_optimal_endpoint(prompt, model_name)
    
    # Setup LLM
    llm = LLM(
        model=f"ollama/{model_name}",
        base_url=base_url,
        temperature=0.3,  # Lower temperature for more consistent code
        max_tokens=1024   # More tokens for complete code
    )
    
    # Create focused code generation prompt
    code_prompt = f"""Generate working {prompt}. 

Requirements:
- Provide ONLY the code, no explanations
- Make it functional and complete
- Use proper syntax and best practices
- Include comments only where necessary

Code:"""
    
    try:
        # Use the correct LLM API method
        start_time = time.time()
        result = llm.call(code_prompt)
        end_time = time.time()
        
        generation_time = end_time - start_time
        console.print(f"⏱️  Generation time: {generation_time:.2f} seconds", style="cyan")
        
        return result
    except Exception as e:
        console.print(f"❌ Error: {e}", style="red")
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
        
        # Save to file
        output_file = Path("output") / f"generated_{prompt.replace(' ', '_')[:30]}.txt"
        output_file.parent.mkdir(exist_ok=True)
        
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write(result)
        
        console.print(f"\n💾 Code saved to: [bold blue]{output_file}[/bold blue]")

if __name__ == "__main__":
    main()