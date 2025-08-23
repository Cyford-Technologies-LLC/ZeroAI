#!/usr/bin/env python3
"""
Hybrid Deployment Example

Shows how to use both local and cloud AI models in the same workflow.
Perfect for cost optimization and performance tuning.
"""

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from ai_crew import AICrewManager
from agents.base_agents import create_custom_agent
from tasks.base_tasks import create_custom_task
from crewai import Crew
from rich.console import Console

console = Console()


def main():
    """Run hybrid deployment example."""
    console.print("🌐 [bold blue]Hybrid Local + Cloud AI Deployment[/bold blue]")
    console.print("=" * 60)
    
    try:
        # Create local manager for basic tasks
        console.print("🏠 Setting up local AI (fast, free)...")
        local_manager = AICrewManager(provider="local")
        
        # Create cloud manager for complex tasks
        console.print("☁️  Setting up cloud AI (powerful, paid)...")
        cloud_manager = AICrewManager(
            provider="openai", 
            model_name="gpt-4"
        )
        
        # Create agents with different capabilities
        local_researcher = create_custom_agent(
            role="Local Research Assistant",
            goal="Gather initial research and basic information",
            backstory="Fast local AI for preliminary research",
            llm=local_manager.llm
        )
        
        cloud_analyst = create_custom_agent(
            role="Expert Cloud Analyst",
            goal="Provide deep analysis and strategic insights",
            backstory="Advanced cloud AI for complex analysis",
            llm=cloud_manager.llm
        )
        
        # Create tasks
        research_task = create_custom_task(
            description="Research basic information about: {topic}",
            agent=local_researcher,
            expected_output="Initial research findings and key facts"
        )
        
        analysis_task = create_custom_task(
            description="Analyze the research and provide strategic insights about: {topic}",
            agent=cloud_analyst,
            expected_output="Deep analysis with strategic recommendations"
        )
        
        # Create hybrid crew
        hybrid_crew = Crew(
            agents=[local_researcher, cloud_analyst],
            tasks=[research_task, analysis_task],
            verbose=True
        )
        
        # Get topic
        topic = input("\n📝 Enter topic for hybrid analysis: ").strip()
        if not topic:
            topic = "AI market opportunities for small businesses"
        
        console.print(f"\n🔍 Hybrid analysis of: [bold green]{topic}[/bold green]")
        console.print("💡 Local AI handles research, Cloud AI provides deep analysis")
        
        # Execute hybrid workflow
        result = hybrid_crew.kickoff(inputs={"topic": topic})
        
        # Display results
        console.print("\n" + "=" * 60)
        console.print("🎯 [bold green]Hybrid AI Results:[/bold green]")
        console.print("=" * 60)
        console.print(result)
        
        # Show cost optimization info
        console.print("\n💰 [bold yellow]Cost Optimization Benefits:[/bold yellow]")
        console.print("• Local AI: $0 for basic research")
        console.print("• Cloud AI: Only pay for complex analysis")
        console.print("• Best of both: Speed + Intelligence")
        
    except Exception as e:
        console.print(f"\n❌ Error: {e}")
        console.print("💡 Make sure both Ollama is running and cloud API keys are set")


if __name__ == "__main__":
    main()