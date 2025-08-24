#!/usr/bin/env python3
"""
Code Generator Example

Direct code generation without research crew overhead.
"""

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from crewai import LLM
from config import config
from smart_router import router
from rich.console import Console

console = Console()

def generate_code(prompt: str):
    """Generate code directly using the optimal model."""
    
    # Get optimal model for coding task
    model_name = router.get_optimal_model(prompt)
    console.print(f"🤖 Using model: {model_name}")
    
    # Setup LLM
    llm = LLM(
        model=f"ollama/{model_name}",
        base_url="http://localhost:11434",
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
        result = llm.call(code_prompt)
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