# src/crews/internal/research/agents.py

from crewai import Agent
from typing import Dict, Any
from distributed_router import DistributedRouter
from config import config
from agents.base_agents import create_researcher, create_analyst

def create_internal_researcher_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    # Pass the specific category to ensure proper learning
    return create_researcher(router, inputs, category="research")

def create_internal_analyst_agent(router: DistributedRouter, inputs: Dict[str, Any]) -> Agent:
    # Pass the specific category to ensure proper learning
    return create_analyst(router, inputs, category="research")