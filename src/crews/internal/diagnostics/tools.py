# src/crews/internal/diagnostics/tools.py (Full script)
import uuid
from datetime import datetime
from pathlib import Path
import re
from typing import List
from rich.console import Console
from crewai.tools import BaseTool

console = Console()


class LogAnalysisTool(BaseTool):
    name: str = "Log Analysis Tool"
    description: str = "Analyzes a string of CrewAI verbose logs to find the root cause of delegation failures. It does not access files directly."

    def _run(self, log_output: str, coworker_names: List[str]) -> str:
        """Parses verbose log output to provide a diagnosis of delegation failures."""
        # --- Check for general errors in the log output ---
        error_pattern = re.compile(r"Error: |Exception: |Traceback")
        error_matches = error_pattern.findall(log_output)
        if error_matches:
            return f"🚨 Diagnosis: The following errors were found in the verbose logs:\n\n{error_matches}"

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


class DiagnosticFileHandlerTool(BaseTool):
    name: str = "Diagnostic File Handler Tool"
    description: str = "Processes, relogs, and deletes error files created by the Team Manager. It reads error files, creates a consolidated diagnostic report, and then cleans up the old files."

    def _run(self, input_data: str = None) -> str:
        """
        Reads, consolidates, and deletes manager-logged error files.
        Input is not used, it is triggered to handle files in the 'errors/' directory.
        """
        error_dir = Path("errors")
        diagnostic_dir = Path("diagnostics")
        diagnostic_dir.mkdir(parents=True, exist_ok=True)

        error_files = list(error_dir.glob("error_*.log"))
        if not error_files:
            return "No manager-logged error files found to process."

        consolidated_report = []
        for error_file in error_files:
            try:
                content = error_file.read_text()
                consolidated_report.append(f"--- Processed File: {error_file.name} ---\n{content}\n")
            except Exception as e:
                consolidated_report.append(f"⚠️ Error reading file {error_file.name}: {e}\n")

        # Create the new diagnostic log file
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        new_log_path = diagnostic_dir / f"diagnostic_agent_errors_{timestamp}.log"
        with open(new_log_path, 'w') as f:
            f.write("\n".join(consolidated_report))

        # Delete the old error files after successful consolidation
        for error_file in error_files:
            try:
                error_file.unlink()
            except Exception as e:
                console.print(f"⚠️ Failed to delete processed file {error_file.name}: {e}", style="yellow")

        return f"Successfully processed {len(error_files)} error files. Consolidated report saved to {new_log_path} and original error files have been deleted."

