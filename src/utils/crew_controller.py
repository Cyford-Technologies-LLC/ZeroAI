import signal
import threading
import time
from typing import Optional, Callable
from rich.console import Console

console = Console()

class CrewController:
    """Controls crew execution with graceful shutdown and real-time communication."""
    
    def __init__(self):
        self.shutdown_requested = False
        self.crew_thread: Optional[threading.Thread] = None
        self.status_callback: Optional[Callable] = None
        self.current_crew = None
        
    def setup_signal_handlers(self):
        """Setup graceful shutdown on Ctrl+C."""
        signal.signal(signal.SIGINT, self._signal_handler)
        signal.signal(signal.SIGTERM, self._signal_handler)
        
    def _signal_handler(self, signum, frame):
        """Handle shutdown signals gracefully."""
        console.print("\n🛑 [yellow]Graceful shutdown requested...[/yellow]")
        self.shutdown_requested = True
        
        if self.current_crew:
            console.print("⏹️ Stopping crew execution...")
            # CrewAI doesn't have built-in stop, so we set flag
            
    def run_with_control(self, crew, inputs, status_callback=None):
        """Run crew with shutdown control and status updates."""
        self.status_callback = status_callback
        self.current_crew = crew
        
        def crew_runner():
            try:
                if self.status_callback:
                    self.status_callback("🚀 Starting crew execution...")
                    
                result = crew.kickoff(inputs=inputs)
                
                if not self.shutdown_requested:
                    if self.status_callback:
                        self.status_callback("✅ Crew completed successfully")
                    return result
                else:
                    if self.status_callback:
                        self.status_callback("⏹️ Crew stopped by user")
                    return {"status": "stopped", "message": "Execution stopped by user"}
                    
            except Exception as e:
                if self.status_callback:
                    self.status_callback(f"❌ Crew error: {str(e)}")
                raise
                
        self.crew_thread = threading.Thread(target=crew_runner)
        self.crew_thread.daemon = True
        self.crew_thread.start()
        
        # Monitor thread and allow real-time interaction
        while self.crew_thread.is_alive():
            if self.shutdown_requested:
                console.print("⏳ Waiting for crew to finish current task...")
                break
            time.sleep(0.5)
            
        return self.crew_thread.join(timeout=5.0)
        
    def is_running(self) -> bool:
        """Check if crew is currently running."""
        return self.crew_thread and self.crew_thread.is_alive()
        
    def request_stop(self):
        """Request graceful stop."""
        self.shutdown_requested = True