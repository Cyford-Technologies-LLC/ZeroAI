# src/utils/custom_logger_callback.py
import json
from datetime import datetime

from crewai.callback import Callback
from rich.console import Console

console = Console()

class CustomLoggerCallback(Callback):
    def __init__(self, output_file: str = None):
        self.output_file = output_file
        self.log_history = []
        self._current_agent = None

    def on_agent_start(self, agent, agent_tool, **kwargs):
        """Called when an agent starts its turn."""
        log_entry = {
            "timestamp": datetime.now().isoformat(),
            "event": "on_agent_start",
            "agent_name": agent.name,
            "tool_input": kwargs.get("tool_input"),
            "tool_name": agent_tool,
        }
        self.log_history.append(log_entry)
        self._current_agent = agent.name

    def on_agent_end(self, agent, output, **kwargs):
        """Called when an agent ends its turn."""
        log_entry = {
            "timestamp": datetime.now().isoformat(),
            "event": "on_agent_end",
            "agent_name": agent.name,
            "output": output.result,
        }
        self.log_history.append(log_entry)
        self._current_agent = None

    def on_tool_end(self, tool, output, **kwargs):
        """Called when a tool finishes executing."""
        if self._current_agent:
            log_entry = {
                "timestamp": datetime.now().isoformat(),
                "event": "on_tool_end",
                "agent_name": self._current_agent,
                "tool_name": tool.name,
                "tool_output": output,
            }
            self.log_history.append(log_entry)

    def on_llm_end(self, llm, output, **kwargs):
        """Called when an LLM call finishes."""
        if self._current_agent:
            log_entry = {
                "timestamp": datetime.now().isoformat(),
                "event": "on_llm_end",
                "agent_name": self._current_agent,
                "llm_output": output.generations[0][0].text,
            }
            self.log_history.append(log_entry)

    def save_log(self):
        """Saves the log history to a JSON file."""
        if self.output_file:
            with open(self.output_file, 'w') as f:
                json.dump(self.log_history, f, indent=2)
            console.print(f"📄 Log history saved to {self.output_file}", style="green")

