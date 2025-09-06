# src/crews/internal/research/crew.py
from crewai import Crew, Process
from typing import Dict, Any, List
from src.distributed_router import DistributedRouter
from src.config import config
from src.crews.internal.research.agents import create_project_manager_agent, create_internal_researcher_agent, \
    create_online_researcher_agent
from src.crews.internal.research.tasks import internal_research_task, online_research_task, project_management_task
from src.crews.internal.diagnostics.agents import create_diagnostic_agent
from src.crews.internal.diagnostics.tasks import create_diagnostics_task
from src.crews.internal.code_fixer.agents import create_code_researcher_agent as create_fixer_researcher, \
    create_coder_agent, create_tester_agent
from src.crews.internal.code_fixer.tasks import analyze_codebase_task, fix_bug_task, \
    write_tests_task as write_fixer_tests_task
from src.crews.internal.developer.agents import create_senior_developer_agent, create_qa_engineer_agent, \
    create_junior_developer_agent
from src.crews.internal.developer.tasks import analyze_codebase_task as analyze_dev_task, fix_bug_task as fix_dev_task, \
    write_tests_task as write_dev_tests_task, run_tests_task as run_dev_tests_task

from langchain_ollama import OllamaEmbeddings

# ollama_embedder_config = {
#     "provider": "ollama",
#     "config": {
#         "model": "mistral-nemo:latest",
#         "base_url": "http://149.36.1.65:11434"
#     }
# }

def get_master_crew(router, tools, project_config, use_new_memory=False) -> Crew:
    """
    Wrapper function to create the master crew.
    """
    inputs = {
        "working_dir": project_config.get("crewai_settings", {}).get("working_directory", "/tmp"),
        "project_name": project_config.get("project", {}).get("name", "unknown"),
        "tools": tools,
    }
    return create_master_crew(router, inputs, full_output=True)


def create_master_crew(router: DistributedRouter, inputs: Dict[str, Any], full_output: bool = False) -> Crew:
    tools = inputs.get("tools", [])

    # Get the LLM from the distributed router once
    crew_llm = router.get_llm_for_role("general")

    # --- Create all agents with unique variable names, passing the LLM ---
    # Research Team
    internal_researcher = create_internal_researcher_agent(router, inputs)
    online_researcher = create_online_researcher_agent(router, inputs)
    project_manager = create_project_manager_agent(router, inputs)

    # Code Fixer Team
    fixer_researcher = create_fixer_researcher(router, inputs)
    coder = create_coder_agent(router, inputs)
    fixer_tester = create_tester_agent(router, inputs)

    # Development Team
    senior_developer = create_senior_developer_agent(router, inputs)
    junior_developer = create_junior_developer_agent(router, inputs)
    qa_engineer = create_qa_engineer_agent(router, inputs)

    # Diagnostic Team
    diagnostic_agent = create_diagnostic_agent(router, inputs, tools)

    all_agents = [
        internal_researcher, online_researcher, project_manager,
        fixer_researcher, coder, fixer_tester,
        senior_developer, junior_developer, qa_engineer,
        diagnostic_agent
    ]

    # --- Create tasks as variables for correct context handling ---
    research_task_var = internal_research_task(internal_researcher, inputs)
    online_task_var = online_research_task(online_researcher, inputs)
    project_task_var = project_management_task(project_manager, inputs)
    diagnostics_task_var = create_diagnostics_task(diagnostic_agent, inputs)

    analyze_fixer_task_var = analyze_codebase_task(fixer_researcher, inputs)
    fix_fixer_bug_task_var = fix_bug_task(coder, inputs, context=[analyze_fixer_task_var])
    write_fixer_tests_var = write_fixer_tests_task(fixer_tester, inputs, context=[fix_fixer_bug_task_var])

    analyze_dev_task_var = analyze_dev_task(qa_engineer, inputs)
    fix_dev_bug_task_var = fix_dev_task(senior_developer, inputs, context=[analyze_dev_task_var])
    write_dev_tests_task_var = write_dev_tests_task(qa_engineer, inputs, context=[fix_dev_bug_task_var])
    run_dev_tests_task_var = run_dev_tests_task(qa_engineer, inputs, context=[write_dev_tests_task_var])

    tasks = [
        research_task_var,
        online_task_var,
        project_task_var,
        diagnostics_task_var,
        analyze_fixer_task_var,
        fix_fixer_bug_task_var,
        write_fixer_tests_var,
        analyze_dev_task_var,
        fix_dev_bug_task_var,
        write_dev_tests_task_var,
        run_dev_tests_task_var
    ]

    # --- Final Crew Creation ---
    return Crew(
        agents=all_agents,
        tasks=tasks,
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=full_output,
        memory=True,
        llm=crew_llm,  # Pass the distributed LLM to the Crew object
        # embedder = ollama_embedder_config
    )

