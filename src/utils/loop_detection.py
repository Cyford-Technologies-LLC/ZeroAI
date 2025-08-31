from collections import deque
from rich.console import Console

console = Console()


class LoopDetector:
    def __init__(self, max_consecutive_repeats: int = 3):
        self.max_repeats = max_consecutive_repeats
        self.output_history = deque(maxlen=max_consecutive_repeats)
        self.loop_detected = False
        console.print(
            f"🔄 Initialized LoopDetector to monitor for {max_consecutive_repeats} consecutive identical outputs.",
            style="cyan")

    def detect(self, agent_output: str) -> None:
        """Adds the new output to history and checks for a loop, setting a flag if detected."""
        self.output_history.append(agent_output.strip())

        if len(self.output_history) == self.max_repeats and len(set(self.output_history)) == 1:
            console.print(f"❌ Loop detected: Same output occurred {self.max_repeats} times in a row.", style="red")
            self.loop_detected = True
