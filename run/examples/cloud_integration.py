#!/usr/bin/env python3
"""
Cloud Integration Example

Demonstrates how to use cloud AI providers (OpenAI, Anthropic, etc.)
instead of or alongside local models.
"""

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from providers.cloud_providers import CloudProviderManager
from agents.base_agents import create_custom_agent
from tasks.base_tasks import create_custom_task
from crewai import Crew
from rich.console import Console
from rich.table import Table

console = Console()


def main():
    """Run cloud integration example."""
    console.print("☁️ [bold blue]Cloud AI Integration Example[/bold blue]")
    console.print("=" * 60)
    
    # Show available providers
    table = Table(title="🌐 Available Cloud Providers")
    table.add_column("Provider", style="cyan", no_wrap=True)
    table.add_column("Models", style="magenta")
    table.add_column("Environment Variable", style="green")
    
    providers = [
        ("OpenAI", "gpt-4, gpt-3.5-turbo", "OPENAI_API_KEY"),
        ("Anthropic", "claude-3-sonnet, claude-3-haiku", "ANTHROPIC_API_KEY"),
        ("Azure OpenAI", "gpt-4, gpt-35-turbo", "AZURE_OPENAI_API_KEY"),
        ("Google", "gemini-pro, gemini-pro-vision", "GOOGLE_API_KEY")
    ]
    
    for provider, models, env_var in providers:
        table.add_row(provider, models, env_var)
    
    console.print(table)
    
    # Get user choice
    choice = input("\n🎯 Select provider (openai/anthropic/azure/google): ").lower().strip()
    
    try:
        # Create LLM based on choice
        if choice == "openai":
            llm = CloudProviderManager.create_openai_llm()
            console.print("✅ Connected to OpenAI GPT-4")
        elif choice == "anthropic":
            llm = CloudProviderManager.create_anthropic_llm()
            console.print("✅ Connected to Anthropic Claude")
        elif choice == "azure":
            llm = CloudProviderManager.create_azure_llm()
            console.print("✅ Connected to Azure OpenAI")
        elif choice == "google":
            llm = CloudProviderManager.create_google_llm()
            console.print("✅ Connected to Google Gemini")
        else:
            console.print("❌ Invalid choice. Using OpenAI as default.")
            llm = CloudProviderManager.create_openai_llm()
        
        # Create agents with cloud LLM
        researcher = create_custom_agent(
            role="Cloud Research Specialist",
            goal="Conduct research using cloud AI capabilities",
            backstory="Expert researcher powered by cloud AI",
            llm=llm
        )
        
        # Create task
        task = create_custom_task(
            description="Research the topic: {topic} and provide comprehensive insights",
            agent=researcher,
            expected_output="Detailed research report with key findings and insights"
        )
        
        # Create crew
        crew = Crew(
            agents=[researcher],
            tasks=[task],
            verbose=True
        )
        
        # Get topic
        topic = input("\n📝 Enter research topic: ").strip()
        if not topic:
            topic = "Latest trends in cloud computing"
        
        console.print(f"\n🔍 Researching: [bold green]{topic}[/bold green]")
        
        # Execute
        result = crew.kickoff(inputs={"topic": topic})
        
        # Display results
        console.print("\n" + "=" * 60)
        console.print("📊 [bold green]Cloud AI Results:[/bold green]")
        console.print("=" * 60)
        console.print(result)
        
    except Exception as e:
        console.print(f"\n❌ Error: {e}")
        console.print("💡 Make sure your API key is set in environment variables")


if __name__ == "__main__":
    main()