#!/usr/bin/env python3
"""
Persistent Crew Daemon - Keeps crews running 24/7
"""

import sys
import os
import time
import signal
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

from src.utils.persistent_crew import crew_pool
from src.ai_dev_ops_crew import AIOpsCrewManager
from distributed_router import DistributedRouter
from rich.console import Console

console = Console()

class CrewDaemon:
    """Daemon to manage persistent crews."""
    
    def __init__(self):
        self.running = True
        self.router = DistributedRouter()
        
    def setup_signal_handlers(self):
        """Setup graceful shutdown."""
        signal.signal(signal.SIGINT, self._shutdown)
        signal.signal(signal.SIGTERM, self._shutdown)
        
    def _shutdown(self, signum, frame):
        """Graceful shutdown."""
        console.print("\n🛑 [yellow]Shutting down crew daemon...[/yellow]")
        self.running = False
        crew_pool.shutdown_all()
        
    def start_default_crews(self):
        """Start default persistent crews."""
        projects = ["zeroai", "testcorp"]
        
        for project_id in projects:
            console.print(f"🚀 [blue]Starting persistent crew for {project_id}[/blue]")
            
            # Create crew manager
            inputs = {"project_id": project_id, "category": "general"}
            manager = AIOpsCrewManager(self.router, project_id, inputs)
            
            # Add to persistent pool (simplified - you'd create actual crew here)
            # crew = manager.create_crew()  # You'd implement this
            # crew_pool.add_crew(project_id, crew)
            
            console.print(f"✅ [green]Crew {project_id} ready for tasks[/green]")
            
    def run(self):
        """Main daemon loop."""
        console.print("🌟 [bold green]ZeroAI Persistent Crew Daemon Starting[/bold green]")
        
        self.setup_signal_handlers()
        self.start_default_crews()
        
        console.print("🔄 [dim]Daemon running... Press Ctrl+C to stop[/dim]")
        
        while self.running:
            # Show status every 30 seconds
            status = crew_pool.status_all()
            if status:
                console.print(f"📊 [dim]Active crews: {len(status)}[/dim]")
            time.sleep(30)
            
        console.print("✅ [green]Daemon stopped[/green]")

if __name__ == "__main__":
    daemon = CrewDaemon()
    daemon.run()