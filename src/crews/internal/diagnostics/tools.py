# src/crews/internal/diagnostics/tools.py
from typing import List
from pathlib import Path
import re
from rich.console import Console
from crewai.tools import BaseTool

console = Console()


class LogAnalysisTool(BaseTool):
    name: str = "Log Analysis Tool"
    # Corrected description to be a static string.
    description: str = "Analyzes a string of CrewAI verbose logs and error files to find the root cause of delegation failures. It prioritizes checking error files first."

    def _run(self, log_output: str, coworker_names: List[str]) -> str:
        """
        Parses verbose log output and checks error files to provide a diagnosis of delegation failures.
        """
        # --- REVISED: Check for errors in the error directory FIRST, aggregating results ---
        error_dir = Path("errors")
        error_diagnoses = []
        if error_dir.exists():
            for error_file in error_dir.glob("*.log"):
                try:
                    with open(error_file, 'r') as f:
                        error_content = f.read()
                        if "ERROR:" in error_content or "Exception:" in error_content:
                            error_diagnoses.append(
                                f"🚨 Diagnosis: Found a manager-logged error in file {error_file.name}:\n\n{error_content}")
                except Exception as e:
                    console.print(f"⚠️ Could not read error file {error_file}: {e}", style="yellow")

        # If errors were found in the error files, return them immediately
        if error_diagnoses:
            return "\n\n".join(error_diagnoses)

        # --- EXISTING: Check for general errors in the verbose log output ---
        error_pattern = re.compile(r"Error: |Exception: |Traceback")
        error_matches = error_pattern.findall(log_output)
        if error_matches:
            return f"🚨 Diagnosis: The following errors were found in the verbose logs:\n\n{error_matches}"

        # --- EXISTING: Check for delegation to a non-existent agent ---
        match = re.search(r"Failed Delegate work to coworker.*?Agent name: (.*?)\n", log_output)
        if match:
            failed_agent_name = match.group(1).strip()
            if failed_agent_name not in coworker_names:
                return f"Diagnosis: The manager attempted to delegate to an unknown agent named '{failed_agent_name}'. Check if this agent exists and if its name is correct."

        # --- EXISTING: Check for recursive delegation loops ---
        delegation_calls = re.findall(r"Delegate work to coworker.*?Agent name: .*?\n", log_output)
        if len(delegation_calls) > 2 and len(set(delegation_calls)) == 1:
            return "Diagnosis: The manager is stuck in a delegation loop, repeatedly delegating the same task. This may indicate the task was not handed off correctly or the sub-agent's response was not understood."

        # --- EXISTING: Check for insufficient planning before delegation ---
        match = re.search(r"Delegate work to coworker.*?Reasoning: (.*?)\n", log_output, re.DOTALL)
        if match and len(match.group(1).strip()) < 50:
            return "Diagnosis: Delegation failed due to insufficient reasoning. The manager needs a more detailed plan before attempting to delegate."

        # --- EXISTING: If no specific pattern is found, provide a general error message ---
        if "Failed Delegate work to coworker" in log_output:
            return "Diagnosis: Delegation failed for an unspecified reason. Please check the log output for contextual clues around the 'Failed Delegate work to coworker' message."

        return "Diagnosis: No delegation failure detected in logs or error files. All tests passed."
