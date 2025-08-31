# src/crews/internal/diagnostics/tools.py
from crewai import Agent
from typing import Dict, Any, List
from pathlib import Path
import re

from rich.console import Console
# --- REVISED IMPORT ---
from crewai.tools import BaseTool

console = Console()

class LogAnalysisTool(BaseTool):
    name: str = "Log Analysis Tool"
    description=f"Use the 'Log Analysis Tool' to analyze the provided logs and diagnose the root cause of a delegation failure. Your analysis must be based solely on the output from the tool.\n\nLogs:\n{full_log_output}",


    def _run(self, log_output: str, coworker_names: List[str]) -> str:
        """
        Parses verbose log output to provide a diagnosis of delegation failures.
        """
        # --- NEW: Check for general errors in the log output ---
        error_pattern = re.compile(r"Error: |Exception: |Traceback")
        error_matches = error_pattern.findall(log_output)

        if error_matches:
            return f"🚨 Diagnosis: The following errors were found in the logs:\n\n{error_matches}"

        # --- NEW: Check for errors in the error directory ---
        error_dir = Path("errors")
        if error_dir.exists():
            for error_file in error_dir.glob("*.log"):
                with open(error_file, 'r') as f:
                    error_content = f.read()
                    if "Error" in error_content.lower():
                        return f"🚨 Diagnosis: Found error in file {error_file.name}:\n\n{error_content}"

        coworker_names_str = "|".join([re.escape(name) for name in coworker_names])
        coworker_delegation_pattern = re.compile(
            r"Delegate work to coworker.*?Agent name: (?!({}))".format(coworker_names_str),
            re.DOTALL
        )

        # Check for delegation to a non-existent agent
        match = re.search(r"Failed Delegate work to coworker.*?Agent name: (.*?)\n", log_output)
        if match:
            failed_agent_name = match.group(1).strip()
            if failed_agent_name not in coworker_names:
                return f"Diagnosis: The manager attempted to delegate to an unknown agent named '{failed_agent_name}'. Check if this agent exists and if its name is correct."

        # Check for recursive delegation loops
        delegation_calls = re.findall(r"Delegate work to coworker.*?Agent name: .*?\n", log_output)
        if len(delegation_calls) > 2 and len(set(delegation_calls)) == 1:
            return "Diagnosis: The manager is stuck in a delegation loop, repeatedly delegating the same task. This may indicate the task was not handed off correctly or the sub-agent's response was not understood."

        # Check for insufficient planning before delegation
        match = re.search(r"Delegate work to coworker.*?Reasoning: (.*?)\n", log_output, re.DOTALL)
        if match and len(match.group(1).strip()) < 50:
            return "Diagnosis: Delegation failed due to insufficient reasoning. The manager needs a more detailed plan before attempting to delegate."

        # If no specific pattern is found, provide a general error message
        if "Failed Delegate work to coworker" in log_output:
            return "Diagnosis: Delegation failed for an unspecified reason. Please check the log output for contextual clues around the 'Failed Delegate work to coworker' message."

        return "Diagnosis: No delegation failure detected. All tests passed."
