# src/utils/custom_logger_callback.py (Revised snippet)
import json
import time
from rich.console import Console

console = Console()


class CustomLogger:
    def __init__(self, output_file: str):
        self.output_file = output_file
        self.log_data = {"execution_start": time.time(), "steps": []}
        console.print(f"📝 Custom logger initialized, logging to '{output_file}'", style="blue")

    def log_step_callback(self, output: any) -> None:
        """
        Logs the output of each agent step.
        """
        step_output = {}
        if hasattr(output, 'result'):
            step_output = {
                "type": "tool_output",
                "result": output.result,
                "tool": output.tool
            }
        else:
            # Handle other types of step output like AgentAction or AgentFinish
            step_output = {
                "type": "agent_action",  # Assuming it's an agent action if not a tool output
                "output": str(output)
            }

        self.log_data["steps"].append({
            "timestamp": time.time(),
            "output": step_output
        })

        console.print(f"📝 Logged step output: {step_output}", style="dim")

    def save_log(self) -> None:
        """
        Saves the complete log data to the specified output file.
        """
        self.log_data["execution_end"] = time.time()
        with open(self.output_file, 'w') as f:
            json.dump(self.log_data, f, indent=4)
        console.print(f"📝 Log saved to '{self.output_file}'", style="green")

