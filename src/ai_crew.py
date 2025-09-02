# src/ai_crew.py (modified with learning integration)

import logging
from typing import Dict, Any, Optional, List
from crewai import Agent, Task, Crew, Process, CrewOutput, TaskOutput
from rich.console import Console
from pydantic import BaseModel, Field
import warnings
import json
import time
import uuid

from langchain_community.llms.ollama import Ollama

# --- Existing imports ---
from src.config import config
from agents.base_agents import create_researcher, create_writer, create_analyst
from tasks.base_tasks import create_research_task, create_writing_task, create_analysis_task
from distributed_router import DistributedRouter

# --- Specialized crew imports ---
from crews.classifier.agents import create_classifier_agent
from crews.coding.crew import create_coding_crew
from crews.math.crew import create_math_crew
from crews.tech_support.crew import create_tech_support_crew
from crews.customer_service.crew import create_customer_service_crew

# --- Specialized agent imports ---
from crews.math.agents import create_mathematician_agent
from crews.coding.agents import create_coding_developer_agent, create_qa_engineer_agent
from crews.tech_support.agents import create_tech_support_agent
from crews.customer_service.agents import create_customer_service_agent

# Initialize console first
console = Console()
logger = logging.getLogger(__name__)

# --- Import learning module ---
try:
    from learning.feedback_loop import feedback_loop, record_task_result
    has_learning = True
    console.print("✅ Learning module loaded successfully", style="green")
except ImportError:
    has_learning = False
    console.print("⚠️ Learning module not available, performance optimization disabled", style="yellow")

    # Create dummy record_task_result function
    def record_task_result(*args, **kwargs):
        console.print("ℹ️ Task result recording skipped (learning module not available)", style="yellow")
        return True


# --- Needed for CrewOutput token_usage compatibility ---
class UsageMetrics(BaseModel):
    total_tokens: Optional[int] = 0
    prompt_tokens: Optional[int] = 0
    completion_tokens: Optional[int] = 0
    successful_requests: Optional[int] = 0


# --- Get model preferences helper function ---
def get_model_preferences_for_category(category: str) -> List[str]:
    """
    Get the preferred models for a specific category based on learning.
    Falls back to default preferences if learning module not available.

    Args:
        category: The task category (e.g., "coding", "math", "research")

    Returns:
        List of model names in order of preference
    """
    # Default model preferences based on category
    default_preferences = {
        "coding": ["codellama:13b", "codellama:7b", "llama3.1:8b", "llama3.2:latest", "llama3.2:1b"],
        "math": ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llama3.2:1b"],
        "research": ["llama3.1:8b", "llama3.2:latest", "gemma2:2b", "llama3.2:1b"],
        "documentation": ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"],
        "customer_service": ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"],
        "tech_support": ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"],
        "general": ["llama3.2:latest", "llama3.1:8b", "gemma2:2b", "llama3.2:1b"],
    }

    # Get the default preferences for this category, or use general if not found
    preferred_models = default_preferences.get(category, default_preferences["general"])

    # If learning module is available, try to get learned preferences
    if has_learning:
        try:
            # Get the learned model preferences
            learned_models = feedback_loop.get_model_preferences(category)

            # If we have learned models, add them to the top of the list
            if learned_models:
                # Add any learned models that aren't already in the list to the front
                for model in reversed(learned_models):
                    if model in preferred_models:
                        # If model exists, remove it first so we can add it to the front
                        preferred_models.remove(model)
                    # Add the model to the front of the list
                    preferred_models.insert(0, model)

                console.print(f"🧠 Using learned model preferences for category '{category}': {preferred_models[:2]}", style="blue")
        except Exception as e:
            console.print(f"⚠️ Error getting learned model preferences: {e}", style="yellow")

    return preferred_models


# --- Plausible definitions for missing agent and task creators with learning integration ---
def create_research_crew(router: DistributedRouter, inputs: Dict[str, Any]) -> Crew:
    """Create research crew with learning-based model preferences."""
    # Get preferred models for research
    preferred_models = get_model_preferences_for_category("research")

    # Add preferred models to inputs
    inputs["preferred_models"] = preferred_models

    researcher = create_researcher(router, inputs)
    writer = create_writer(router, inputs)
    if not researcher or not isinstance(researcher, Agent):
        raise ValueError("Failed to create researcher agent.")
    if not writer or not isinstance(writer, Agent):
        raise ValueError("Failed to create writer agent.")
    research_task = create_research_task(inputs, researcher)
    writing_task = create_writing_task(inputs, writer)
    return Crew(
        agents=[researcher, writer],
        tasks=[research_task, writing_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=True
    )


def create_analysis_crew(router: DistributedRouter, inputs: Dict[str, Any]) -> Crew:
    """Create analysis crew with learning-based model preferences."""
    # Get preferred models for analysis
    preferred_models = get_model_preferences_for_category("research")

    # Add preferred models to inputs
    inputs["preferred_models"] = preferred_models

    analyst = create_analyst(router, inputs)
    analysis_task = create_analysis_task(inputs, analyst)
    return Crew(
        agents=[analyst],
        tasks=[analysis_task],
        process=Process.sequential,
        verbose=config.agents.verbose,
        full_output=True
    )


# --- The core AICrewManager class with learning integration ---
class AICrewManager:
    """Manages AI crew creation and execution with a robust fallback and learning integration."""

    def __init__(self, distributed_router_instance: DistributedRouter, **kwargs):
        if not isinstance(distributed_router_instance, DistributedRouter):
            logging.error(
                f"FATAL: Router is not a DistributedRouter instance. Type: {type(distributed_router_instance)}, Value: {distributed_router_instance}")
            raise TypeError(
                f"Expected router to be a DistributedRouter instance, but got {type(distributed_router_instance)}")
        self.router = distributed_router_instance
        self.inputs = kwargs.get('inputs', {})
        self.category = self.inputs.get('category', 'general')
        self.task_description = self.inputs.get('topic', '')
        self.llm_instance = None
        self.task_id = self.inputs.get('task_id', str(uuid.uuid4()))
        self.start_time = None
        self.model_used = "unknown"
        self.peer_used = "unknown"

        logging.info(f"AICrewManager.__init__: self.inputs type={type(self.inputs)}, content={self.inputs}")
        logging.info(f"AICrewManager.__init__: self.router instance stored. Type: {type(self.router)}")

    def execute_crew(self, router: DistributedRouter, inputs: Dict[str, Any]) -> CrewOutput:
        """Executes the appropriate crew based on the category with learning integration."""
        # Start timing execution
        self.start_time = time.time()

        if isinstance(inputs, str):
            logging.warning(f"Received string input from API, converting to dictionary: {inputs}")
            inputs = {"topic": inputs, "category": "auto"}
        if not isinstance(inputs, dict):
            logging.error(f"Received non-dictionary inputs of type {type(inputs)}: {inputs}")
            raise TypeError(f"Expected 'inputs' to be a dictionary, but received type: {type(inputs)}. "
                            f"Received content: {inputs}")

        logging.info(f"AICrewManager.execute_crew: inputs type={type(inputs)}, content={inputs}")
        logging.info(
            f"AICrewManager.execute_crew: router instance check before classification. Type: {type(self.router)}")

        self.inputs = inputs
        self.task_id = inputs.get('task_id', self.task_id)
        prompt = inputs.get('topic', '')
        category = inputs.get('category', 'auto')

        # Auto-classify if needed
        if category == "auto":
            category = self._classify_task(inputs)
            if not category:
                error_msg = "Auto-classification failed."
                self._record_failure(prompt, "auto", error_msg)
                raise Exception(error_msg)
            inputs['category'] = category
            self.category = category

        # Get preferred models for this category
        preferred_models = get_model_preferences_for_category(category)
        inputs['preferred_models'] = preferred_models

        # Create the crew
        crew = self.create_crew_for_category(self.router, inputs)

        # Execute the crew with error handling
        try:
            with warnings.catch_warnings():
                warnings.simplefilter("ignore", DeprecationWarning)
                result = crew.kickoff()

                # Record successful execution
                end_time = time.time()

                # Extract token usage
                token_usage = None
                if hasattr(result, "token_usage"):
                    token_usage = result.token_usage

                # Record successful task completion
                if has_learning:
                    record_task_result(
                        task_id=self.task_id,
                        prompt=prompt,
                        category=category,
                        model_used=self.model_used,
                        peer_used=self.peer_used,
                        start_time=self.start_time,
                        end_time=end_time,
                        success=True,
                        error_message=None,
                        git_changes=None,
                        token_usage=token_usage
                    )

                return result
        except Exception as e:
            console.print(f"❌ Error during crew execution AI : {e}", style="red")

            # Record failed execution
            self._record_failure(prompt, category, str(e))

            return CrewOutput(tasks_output=[], raw=f"Error: {e}", token_usage=UsageMetrics())

    def _record_failure(self, prompt: str, category: str, error_message: str) -> None:
        """Record a failed task execution."""
        end_time = time.time()

        if has_learning:
            record_task_result(
                task_id=self.task_id,
                prompt=prompt,
                category=category,
                model_used=self.model_used,
                peer_used=self.peer_used,
                start_time=self.start_time or time.time() - 1,
                end_time=end_time,
                success=False,
                error_message=error_message,
                git_changes=None,
                token_usage=None
            )

    def create_crew_for_category(self, router: DistributedRouter, inputs: Dict[str, Any]) -> Crew:
        """Create the appropriate crew for a category with learning integration."""
        category = inputs.get('category', 'general')

        # Pass model preferences to all crew creators
        if 'preferred_models' not in inputs:
            inputs['preferred_models'] = get_model_preferences_for_category(category)

        if category == 'coding':
            return create_coding_crew(router, inputs)
        elif category == 'math':
            return create_math_crew(router, inputs)
        elif category == 'research':
            return create_research_crew(router, inputs)
        elif category in ['customer_service', 'general']:
            # Call without specialist_agents
            return create_customer_service_crew(router, inputs)
        elif category == 'tech_support':
            return create_tech_support_crew(router, inputs)
        else:
            # Fallback to customer service crew for any other unhandled category
            return create_customer_service_crew(router, inputs)

    def _classify_task(self, inputs: Dict[str, Any]) -> Optional[str]:
        """
        Helper method to run the classification crew and return the category.
        """
        logging.info(f"AICrewManager._classify_task: inputs type={type(inputs)}, content={inputs}")
        logging.info(
            f"AICrewManager._classify_task: router instance check before agent creation. Type: {type(self.router)}")
        if not isinstance(self.router, DistributedRouter):
            console.print(
                "⚠️ Failed to get optimal LLM for classifier via router: 'self.router' is not a DistributedRouter instance.",
                style="yellow")
            return "general"
        try:
            # Get preferred models for classifier
            preferred_models = get_model_preferences_for_category("general")
            inputs['preferred_models'] = preferred_models

            classifier_agent = create_classifier_agent(self.router, inputs)
            classifier_task = Task(
                description=f"""
                Classify the following user inquiry into one of these categories: 'math', 'coding', 'research', 'customer_service', or 'general'.
                Inquiry: {inputs.get('topic')}.
                Provide ONLY the single word category name as your final output, do not include any other text or formatting.
                """,
                agent=classifier_agent,
                expected_output="A single word representing the category: math, coding, research, or general.",
            )
            classifier_crew = Crew(
                agents=[classifier_agent],
                tasks=[classifier_task],
                verbose=config.agents.verbose,
                full_output=True
            )
            classification_result = classifier_crew.kickoff()

            # FIX: Dump the communication object here
            console.print("\n" + "=" * 60)
            console.print("📊 [bold green]Classifier Communication Object:[/bold green]")

            # Attempt to dump using a more robust method
            try:
                console.print(json.dumps(classification_result.model_dump(), indent=2))
            except Exception as e:
                console.print(f"Failed to dump as JSON: {e}", style="yellow")
                console.print(classification_result)

            console.print("=" * 60 + "\n")

            if classification_result and classification_result.tasks_output:
                last_task_output = classification_result.tasks_output[-1]
                if isinstance(last_task_output, TaskOutput) and last_task_output.raw:
                    # Get the model and peer used for the classification task
                    if hasattr(classifier_agent.llm, "model"):
                        self.model_used = classifier_agent.llm.model
                    if hasattr(classifier_agent.llm, "base_url"):
                        self.peer_used = classifier_agent.llm.base_url

                    return last_task_output.raw.strip().lower()
            return "general"
        except Exception as e:
            console.print(f"❌ Error during classification: {e}", style="red")
            return "general"

# Export the function for individual use
def run_ai_crew_securely(router: DistributedRouter, inputs: Dict[str, Any]) -> CrewOutput:
    """Run the AI crew with the given inputs."""
    manager = AICrewManager(router, inputs=inputs)
    return manager.execute_crew(router, inputs)