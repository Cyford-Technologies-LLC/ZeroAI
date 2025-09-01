from crewai import Agent
from langchain_ollama import OllamaLLM
from src.crews.internal.tools.scheduling_tool import SchedulingTool
from src.config import config


# You must also define a scheduler crew in `src/crews/internal/scheduler/crew.py`
# and ensure it is properly imported.

def create_scheduler_agent(router, inputs, tools=None):
    return Agent(
        role="Scheduler",
        goal="Schedule events and appointments based on requests from the team manager.",
        backstory="An expert in calendar management, proficient at scheduling, organizing, and managing events and appointments efficiently.",
        tools=[SchedulingTool()],
        llm=OllamaLLM(model="ollama/llama3.1:8b", base_url="http://149.36.1.65:11434"),
        allow_delegation=False,
        verbose=config.agents.verbose
    )
