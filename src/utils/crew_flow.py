# src/utilities/crew_flow.py

from typing import Dict, Any

def run_project_flow(research_crew_factory, dev_crew_factory, project_config: Dict[str, Any], router, tools) -> Any:
    """
    Orchestrates the entire project workflow by running the research and development crews sequentially.
    """
    # 1. Kick off the research crew
    print("\n--- Starting Research Crew ---\n")
    research_crew = research_crew_factory(router, tools, project_config)
    research_output = research_crew.kickoff()
    print("\n--- Research Crew Finished ---\n")

    # 2. Extract key information from the research output
    # This might require some parsing logic depending on the research crew's output format
    # For now, we'll pass the entire research output as context
    dev_inputs = {
        "project_config": research_output,  # Pass research output as input to the dev crew
        "project_name": project_config.get("project", {}).get("name", "unknown"),
        "working_dir": project_config.get("crewai_settings", {}).get("working_directory", "/tmp"),
        "bug_id": project_config.get("issue", {}).get("id", "unknown")
    }

    # 3. Kick off the development crew
    print("\n--- Starting Development Crew ---\n")
    dev_crew = dev_crew_factory(router, tools, project_config, dev_inputs)
    final_result = dev_crew.kickoff(inputs=dev_inputs)
    print("\n--- Development Crew Finished ---\n")

    return final_result
