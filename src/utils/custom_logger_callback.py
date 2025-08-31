import json
from datetime import datetime
from rich.console import Console

console = Console()


class CustomLogger:
    """A helper class to manage logging output."""

    def __init__(self, output_file: str = None):
        self.output_file = output_file
        self.log_history = []

    def log_step(self, step_output):
        """Logs the output of a crew step."""
        log_entry = {
            "timestamp": datetime.now().isoformat(),
            "event": "on_step_callback",
            "output": str(step_output),
        }
        self.log_history.append(log_entry)

    def log_task(self, task_output):
        """Logs the output of a task."""
        log_entry = {
            "timestamp": datetime.now().isoformat(),
            "event": "on_task_callback",
            "output": str(task_output),
        }
        self.log_history.append(log_entry)

    def save_log(self):
        """Saves the log history to a JSON file."""
        if self.output_file:
            try:
                with open(self.output_file, 'w') as f:
                    json.dump(self.log_history, f, indent=2)
                console.print(f"📄 Log history saved to {self.output_file}", style="green")
            except Exception as e:
                console.print(f"❌ Error saving log: {e}", style="red")


# You can still import this class from other modules
__all__ = ['CustomLogger']
