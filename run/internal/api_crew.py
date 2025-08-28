#!/usr/bin/env python3
"""
API Crew for Customer Service

This crew demonstrates a customer service agent that can handle inquiries and,
if necessary, delegate complex issues to specialized crews.
"""
try:
    __import__('pysqlite3')
    import sys
    sys.modules['sqlite3'] = sys.modules.pop('pysqlite3')
except ImportError:
    pass

import sys
import os
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

from ai_crew import AICrewManager
from cache_manager import cache
from distributed_router import distributed_router
from rich.console import Console

# Import necessary CrewAI components for creating agents and tasks
from crewai import Agent, Task, Crew, Process

console = Console()

def create_customer_service_agent(llm):
    return Agent(
        role="Customer Service Representative",
        goal="Handle customer inquiries, answer questions, and delegate complex issues.",
        backstory=(
            "You are a friendly and efficient customer service representative. "
            "Your job is to understand the customer's request and provide a solution "
            "or delegate it to the appropriate specialized crew if needed. "
            "You always start by greeting the customer and confirming their request."
        ),
        llm=llm,
        # The agent will need tools or delegation capabilities defined here
        # For this example, we will let the LLM's reasoning handle the delegation implicitly
        verbose=True,
        allow_delegation=True
    )

def create_customer_service_task(agent, topic):
    return Task(
        description=f"Process the following customer inquiry: {topic}",
        agent=agent,
        expected_output="A polite and helpful response that addresses the customer's query. If the query requires specialized knowledge, the response should indicate that it is being escalated to the correct team."
    )

def create_customer_service_crew(llm, topic):
    customer_service_agent = create_customer_service_agent(llm)
    customer_service_task = create_customer_service_task(customer_service_agent, topic)

    return Crew(
        agents=[customer_service_agent],
        tasks=[customer_service_task],
        process=Process.sequential,
        verbose=True
    )

def main():
    """Run the customer service crew example."""
    console.print("🤖 [bold blue]Self-Hosted Agentic AI - Customer Service Crew[/bold blue]")
    console.print("=" * 60)

    try:
        # Define the customer inquiry
        topic = input("\n📝 Enter your customer inquiry: ").strip()
        if not topic:
            topic = "I have a question about my last payment and want to know my account balance."

        # Initialize the AI Crew Manager with task context and the router instance
        console.print("🔧 Initializing AI Crew Manager...")
        manager = AICrewManager(distributed_router, inputs={"topic": topic})

        # Create the customer service crew
        console.print("👥 Creating customer service crew...")
        # Note: You'll need to update AICrewManager to create this specific crew type
        crew = manager.create_customer_service_crew(inputs={"topic": topic})

        console.print(f"\n🔍 Processing inquiry: [bold green]{topic}[/bold green]")

        # Execute the crew
        result = manager.execute_crew(crew, {"topic": topic})

        # Display results
        console.print("\n" + "=" * 60)
        console.print("📊 [bold green]Inquiry Results:[/bold green]")
        console.print("=" * 60)
        console.print(result)

        # Save results to file
        output_file = Path("output") / f"customer_service_{topic.replace(' ', '_')[:30]}.txt"
        output_file.parent.mkdir(exist_ok=True)

        with open(output_file, 'w', encoding='utf-8') as f:
            f.write(f"Customer Inquiry: {topic}\n")
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
