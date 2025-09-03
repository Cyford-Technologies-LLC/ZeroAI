#!/usr/bin/env python3
"""
Quick Task Submission - Submit tasks to persistent crews instantly
"""

import requests
import sys
from rich.console import Console

console = Console()

def submit_task(prompt: str, project_id: str = "zeroai", category: str = "general"):
    """Submit task to persistent crew."""
    
    url = "http://localhost:3939/add_task/"
    
    data = {
        "project_id": project_id,
        "prompt": prompt,
        "category": category
    }
    
    try:
        response = requests.post(url, json=data)
        response.raise_for_status()
        
        result = response.json()
        console.print(f"✅ [green]Task submitted![/green]")
        console.print(f"📋 Task ID: {result['task_id']}")
        console.print(f"📊 Status: {result['status']}")
        
        return result['task_id']
        
    except requests.exceptions.ConnectionError:
        console.print("❌ [red]Cannot connect to persistent crew API[/red]")
        console.print("💡 [dim]Start daemon: python run/internal/persistent_crew_daemon.py[/dim]")
        return None
        
    except Exception as e:
        console.print(f"❌ [red]Error: {e}[/red]")
        return None

def check_status(project_id: str = "zeroai"):
    """Check crew status."""
    
    url = f"http://localhost:3939/status/{project_id}"
    
    try:
        response = requests.get(url)
        response.raise_for_status()
        
        status = response.json()
        console.print(f"📊 [blue]Crew Status for {project_id}:[/blue]")
        console.print(f"🟢 Running: {status['running']}")
        console.print(f"📋 Queue: {status['queue_size']} tasks")
        console.print(f"⚡ Current: {status['current_task'] or 'Idle'}")
        
    except Exception as e:
        console.print(f"❌ [red]Error checking status: {e}[/red]")

def main():
    """Main function."""
    if len(sys.argv) < 2:
        console.print("Usage: python examples/quick_task_submit.py \"Your task here\"")
        console.print("       python examples/quick_task_submit.py status")
        return
        
    if sys.argv[1] == "status":
        check_status()
    else:
        prompt = " ".join(sys.argv[1:])
        submit_task(prompt)

if __name__ == "__main__":
    main()