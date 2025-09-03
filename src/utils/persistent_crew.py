import threading
import queue
import time
from typing import Dict, Any, Optional
from rich.console import Console

console = Console()

class PersistentCrew:
    """Keeps crews running 24/7 with task queue."""
    
    def __init__(self, crew, project_id):
        self.crew = crew
        self.project_id = project_id
        self.task_queue = queue.Queue()
        self.running = False
        self.worker_thread = None
        self.current_task = None
        
    def start(self):
        """Start persistent crew worker."""
        if self.running:
            return
            
        self.running = True
        self.worker_thread = threading.Thread(target=self._worker_loop)
        self.worker_thread.daemon = True
        self.worker_thread.start()
        console.print(f"🚀 [green]Persistent crew started for {self.project_id}[/green]")
        
    def add_task(self, task_inputs: Dict[str, Any]) -> str:
        """Add task to queue, returns task ID."""
        task_id = f"task_{int(time.time())}"
        task_inputs["task_id"] = task_id
        self.task_queue.put(task_inputs)
        console.print(f"📋 [blue]Task {task_id} queued[/blue]")
        return task_id
        
    def _worker_loop(self):
        """Main worker loop - runs 24/7."""
        console.print("🔄 [dim]Worker loop started, waiting for tasks...[/dim]")
        
        while self.running:
            try:
                # Wait for task (blocks until available)
                task_inputs = self.task_queue.get(timeout=1.0)
                self.current_task = task_inputs
                
                console.print(f"⚡ [yellow]Processing: {task_inputs.get('prompt', 'Unknown task')}[/yellow]")
                
                # Execute task
                result = self.crew.kickoff(inputs=task_inputs)
                
                console.print(f"✅ [green]Completed task {task_inputs.get('task_id')}[/green]")
                self.current_task = None
                
            except queue.Empty:
                # No tasks, continue waiting
                continue
            except Exception as e:
                console.print(f"❌ [red]Task error: {e}[/red]")
                self.current_task = None
                
    def stop(self):
        """Stop persistent crew."""
        self.running = False
        if self.worker_thread:
            self.worker_thread.join(timeout=5.0)
        console.print(f"🛑 [yellow]Persistent crew stopped for {self.project_id}[/yellow]")
        
    def status(self) -> Dict[str, Any]:
        """Get crew status."""
        return {
            "running": self.running,
            "queue_size": self.task_queue.qsize(),
            "current_task": self.current_task.get("prompt") if self.current_task else None
        }

class CrewPool:
    """Manages multiple persistent crews."""
    
    def __init__(self):
        self.crews: Dict[str, PersistentCrew] = {}
        
    def add_crew(self, project_id: str, crew) -> PersistentCrew:
        """Add crew to pool."""
        persistent_crew = PersistentCrew(crew, project_id)
        self.crews[project_id] = persistent_crew
        persistent_crew.start()
        return persistent_crew
        
    def get_crew(self, project_id: str) -> Optional[PersistentCrew]:
        """Get crew by project ID."""
        return self.crews.get(project_id)
        
    def add_task(self, project_id: str, task_inputs: Dict[str, Any]) -> str:
        """Add task to specific crew."""
        crew = self.get_crew(project_id)
        if not crew:
            raise ValueError(f"No crew found for project {project_id}")
        return crew.add_task(task_inputs)
        
    def status_all(self) -> Dict[str, Dict[str, Any]]:
        """Get status of all crews."""
        return {pid: crew.status() for pid, crew in self.crews.items()}
        
    def shutdown_all(self):
        """Shutdown all crews."""
        for crew in self.crews.values():
            crew.stop()
        self.crews.clear()

# Global crew pool
crew_pool = CrewPool()