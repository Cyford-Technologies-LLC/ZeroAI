import threading
import time
from rich.console import Console
from rich.live import Live
from rich.panel import Panel
from rich.text import Text

console = Console()

class InteractiveMonitor:
    """Real-time monitoring and interaction with running crews."""
    
    def __init__(self, controller):
        self.controller = controller
        self.status_messages = []
        self.user_commands = []
        self.monitoring = False
        
    def start_monitoring(self):
        """Start real-time monitoring in separate thread."""
        self.monitoring = True
        monitor_thread = threading.Thread(target=self._monitor_loop)
        monitor_thread.daemon = True
        monitor_thread.start()
        
        # Start input handler
        input_thread = threading.Thread(target=self._input_handler)
        input_thread.daemon = True
        input_thread.start()
        
    def _monitor_loop(self):
        """Main monitoring loop with live display."""
        with Live(self._create_status_panel(), refresh_per_second=2) as live:
            while self.monitoring and self.controller.is_running():
                live.update(self._create_status_panel())
                time.sleep(0.5)
                
    def _input_handler(self):
        """Handle user input while crew is running."""
        console.print("\n[bold green]Interactive Communication:[/bold green]")
        console.print("• Ask questions: 'What are you working on?'")
        console.print("• Give instructions: 'Focus on security issues'")
        console.print("• Commands: 'status', 'stop', 'help'")
        console.print("• Press Ctrl+C for immediate stop\n")
        
        while self.monitoring and self.controller.is_running():
            try:
                user_input = input("💬 You: ").strip()
                
                if user_input.lower() == 'stop':
                    self.controller.request_stop()
                    console.print("🛑 [yellow]Stop requested...[/yellow]")
                    break
                    
                elif user_input.lower() == 'status':
                    if self.status_messages:
                        console.print(f"📊 Latest: {self.status_messages[-1]}")
                    else:
                        console.print("📊 No status updates yet")
                        
                elif user_input.lower() == 'help':
                    console.print("\n[bold]Available Options:[/bold]")
                    console.print("• Ask any question about current work")
                    console.print("• Give new instructions or priorities")
                    console.print("• 'status' - Show current status")
                    console.print("• 'stop' - Request graceful shutdown")
                    
                elif user_input:
                    # Handle as question/instruction to crew
                    console.print(f"🤖 [dim]Crew received: {user_input}[/dim]")
                    self.user_commands.append(user_input)
                    # TODO: Send to crew for processing
                    
            except (EOFError, KeyboardInterrupt):
                self.controller.request_stop()
                break
                
    def _create_status_panel(self):
        """Create status display panel."""
        status_text = Text()
        
        if self.controller.is_running():
            status_text.append("🟢 RUNNING", style="bold green")
        else:
            status_text.append("🔴 STOPPED", style="bold red")
            
        if self.status_messages:
            status_text.append(f"\n\nLatest: {self.status_messages[-1]}")
            
        return Panel(status_text, title="Crew Status", border_style="blue")
        
    def add_status(self, message):
        """Add status message."""
        self.status_messages.append(f"{time.strftime('%H:%M:%S')} - {message}")
        if len(self.status_messages) > 10:
            self.status_messages.pop(0)
            
    def stop_monitoring(self):
        """Stop monitoring."""
        self.monitoring = False