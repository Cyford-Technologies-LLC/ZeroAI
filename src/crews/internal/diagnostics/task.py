# Example of task setup (not a file to edit, but for illustration)
from crewai import Crew, Task

# ... (Assume imports and other setup) ...

# Create the diagnostic agent
diagnostic_agent = create_diagnostic_agent(router, inputs={}, coworker_names=["Developer", "Tester"])

# The task is now to handle files *and* analyze logs.
diagnostic_task = Task(
    description=f"""
    1. First, use the 'Diagnostic File Handler Tool' to process and archive any error files from previous runs.
    2. Then, use the 'Log Analysis Tool' to analyze the current verbose logs below and diagnose the delegation failure.

    Logs:
    {full_log_output}
    """,
    agent=diagnostic_agent,
    expected_output="A concise explanation of the delegation failure, referencing any found error files and logs, and potential fixes."
)

diagnostic_crew = Crew(
    agents=[diagnostic_agent],
    tasks=[diagnostic_task],
    verbose=True
)

diagnostic_result = diagnostic_crew.kickoff()
