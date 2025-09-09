#!/usr/bin/env python3
"""
Test script to verify signal handling works correctly
"""
import signal
import sys
import time
from rich.console import Console

console = Console()

# Global flag for graceful shutdown
shutdown_requested = False

def signal_handler(signum, frame):
    """Handle Ctrl+C gracefully"""
    global shutdown_requested
    shutdown_requested = True
    console.print("\n\n🛑 [bold yellow]Shutdown requested. Cleaning up...[/bold yellow]")
    console.print("Press Ctrl+C again to force exit.", style="dim")
    
    # Set a second handler for force exit
    signal.signal(signal.SIGINT, lambda s, f: sys.exit(1))

# Register signal handler
signal.signal(signal.SIGINT, signal_handler)

def main():
    console.print("🚀 [bold blue]Testing signal handling...[/bold blue]")
    console.print("Press Ctrl+C to test graceful shutdown")
    
    try:
        for i in range(100):
            if shutdown_requested:
                console.print("Shutdown detected. Exiting gracefully.", style="green")
                return
                
            console.print(f"Working... {i}/100", style="dim")
            time.sleep(1)
            
        console.print("✅ Test completed normally", style="green")
        
    except KeyboardInterrupt:
        console.print("\n🛑 [bold yellow]Caught KeyboardInterrupt[/bold yellow]")
        return

if __name__ == "__main__":
    main()