#!/usr/bin/env python3
"""
Resume Improvement Module

Uses distributed models to improve resumes through AI enhancement.
"""

import sys
import os
import time
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from crewai import LLM, Agent, Task, Crew
from distributed_router import distributed_router
from agent_communication import agent_comm
from peer_discovery import peer_discovery
from rich.console import Console

console = Console()

def list_resume_capable_nodes():
    """List nodes with resume-optimized models."""
    console.print("\n🔍 [bold blue]Scanning network for resume-capable nodes...[/bold blue]")

    resume_models = ["mistral", "qwen2.5:7b", "llama3.1:8b", "gemma2"]
    capable_nodes = []

    for peer in peer_discovery.peers.values():
        if not peer.capabilities.available:
            continue

        peer_models = set(peer.capabilities.models)
        has_models = [model for model in resume_models if model in peer_models]

        if has_models:
            capable_nodes.append((peer, has_models))

    if capable_nodes:
        console.print("\n✅ [bold green]Found resume-capable nodes:[/bold green]")
        for peer, models in capable_nodes:
            status = "🟢" if peer.capabilities.available else "🔴"
            gpu_info = f", GPU: {peer.capabilities.gpu_memory_gb:.1f}GB" if peer.capabilities.gpu_memory_gb > 0 else ""
            console.print(f"{status} {peer.name} ({peer.ip}) - RAM: {peer.capabilities.memory_gb:.1f}GB{gpu_info}")
            console.print(f"   Resume models: {', '.join(models)}")
    else:
        console.print("\n⚠️ [bold yellow]No resume-capable nodes found in network[/bold yellow]")

    return capable_nodes

def get_best_resume_model():
    """Find the best available model for resume improvement."""
    # Model preference order
    model_preference = ["mistral", "qwen2.5:7b", "llama3.1:8b", "gemma2", "llama3.2:1b"]

    # First check if peers have these models
    for model in model_preference:
        best_peer = peer_discovery.get_best_peer(model=model)
        if best_peer:
            return model, best_peer

    # If not found on peers, check local models
    local_models = peer_discovery._get_available_models()
    for model in model_preference:
        if model in local_models:
            return model, None

    # If still nothing, return default model
    if local_models:
        return local_models[0], None
    return "llama3.2:1b", None

def improve_resume(resume_text):
    """Improve a resume using AI."""
    console.print("🚀 [bold blue]ZeroAI Resume Improvement Module[/bold blue]")
    console.print("=" * 50)

    # First scan the network for capable nodes
    capable_nodes = list_resume_capable_nodes()

    # Get best available model and node
    model_name, best_peer = get_best_resume_model()

    if best_peer:
        console.print(f"\n✅ Selected model: [bold blue]{model_name}[/bold blue] on node: {best_peer.name}")
        processing_location = f"remote:{best_peer.name}"
        base_url = f"http://{best_peer.ip}:11434"
    else:
        console.print(f"\n✅ Selected model: [bold blue]{model_name}[/bold blue] (local processing)")
        processing_location = "local"
        base_url = "http://localhost:11434"

    # Try using peer agent directly if available
    if best_peer:
        console.print("🔄 Attempting direct processing with peer agent...")
        try:
            start_time = time.time()
            task_data = {
                "type": "resume_improvement",
                "content": resume_text,
                "model": model_name,
                "temperature": 0.7,
                "max_tokens": 1024
            }
            response = agent_comm.send_task_to_peer(best_peer.ip, task_data)

            if response and response.get("success"):
                result = response.get("response")
                end_time = time.time()
                generation_time = end_time - start_time
                console.print(f"⏱️  Generation time: {generation_time:.2f} seconds", style="cyan")
                return result
            else:
                console.print("❌ Peer agent processing failed", style="red")
        except Exception as e:
            console.print(f"❌ Error with peer agent: {e}", style="red")

    # Fallback to CrewAI processing
    console.print("🔄 Using CrewAI for resume improvement...", style="yellow")

    try:
        # Create LLM instance
        llm = LLM(
            model=f"ollama/{model_name}",
            base_url=base_url,
            temperature=0.7,
            max_tokens=1024
        )

        # Create the resume analyzer agent
        analyzer = Agent(
            role="Resume Analyzer",
            goal="Analyze resumes and identify areas for improvement",
            backstory="You are an expert resume analyst with years of experience helping job seekers land interviews",
            llm=llm
        )

        # Create the resume improver agent
        improver = Agent(
            role="Resume Improver",
            goal="Enhance resumes with powerful language and proper formatting",
            backstory="You are a professional resume writer who knows exactly what hiring managers and ATS systems look for",
            llm=llm
        )

        # Create analysis task
        analysis_task = Task(
            description=f"Analyze this resume and identify areas for improvement:\n\n{resume_text}",
            expected_output="Detailed analysis of resume strengths and weaknesses",
            agent=analyzer
        )

        # Create improvement task
        improvement_task = Task(
            description=f"Improve this resume based on the analysis. Keep the same general structure but enhance the language, achievements, and formatting:\n\n{resume_text}",
            expected_output="An improved version of the resume with enhanced language and formatting",
            agent=improver,
            dependencies=[analysis_task]
        )

        # Create and run the crew
        crew = Crew(
            agents=[analyzer, improver],
            tasks=[analysis_task, improvement_task],
            verbose=True
        )

        console.print("\n🔍 Starting resume improvement process...")
        start_time = time.time()
        result = crew.kickoff()
        end_time = time.time()

        generation_time = end_time - start_time
        console.print(f"⏱️  Generation time: {generation_time:.2f} seconds", style="cyan")

        return result
    except Exception as e:
        console.print(f"❌ Error during processing: {e}", style="red")
        console.print("💡 Make sure Ollama is running and has required models installed")
        return None

def main():
    # Get resume content
    console.print("\n📄 Enter your resume text (or press Enter to use sample):")
    resume_text = input().strip()

    if not resume_text:
        resume_text = """
JOHN DOE
Software Developer
email@example.com | (555) 123-4567 | linkedin.com/in/johndoe

PROFESSIONAL SUMMARY
Software developer with 3 years of experience in web development and application design.

SKILLS
Programming: JavaScript, Python, HTML, CSS
Frameworks: React, Node.js
Tools: Git, Docker

EXPERIENCE
Junior Developer, ABC Company
2020 - Present
- Worked on website development
- Fixed bugs in existing applications
- Helped with code reviews

Intern, XYZ Tech
2019 - 2020
- Assisted senior developers
- Learned company workflows
- Participated in team meetings

EDUCATION
Bachelor of Science in Computer Science
University College, Graduated 2019
        """

    # Improve the resume
    improved_resume = improve_resume(resume_text)

    if improved_resume:
        console.print("\n" + "=" * 50)
        console.print("📝 [bold green]Improved Resume:[/bold green]")
        console.print("=" * 50)
        print(improved_resume)

        # Save to file
        output_file = Path("output") / f"improved_resume_{int(time.time())}.txt"
        output_file.parent.mkdir(exist_ok=True)

        with open(output_file, 'w', encoding='utf-8') as f:
            f.write(improved_resume)

        console.print(f"\n💾 Improved resume saved to: [bold blue]{output_file}[/bold blue]")

if __name__ == "__main__":
    main()