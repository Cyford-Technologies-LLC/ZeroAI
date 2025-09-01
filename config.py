import json
import os
from pathlib import Path
from typing import Optional, Dict, Any

from pydantic import BaseModel, Field, ValidationError, SecretStr
from dotenv import find_dotenv, load_dotenv

# Define the path to the JSON configuration file
CONFIG_FILE = Path("config.json")


class OllamaConfig(BaseModel):
    """Configuration for the Ollama LLM service."""
    model: str = "ollama/llama3.1:8b"
    base_url: str = "http://149.36.1.65:11434"


class Settings(BaseModel):
    """Application settings, with Pydantic validation."""
    app_name: str = "ZeroAI"
    agents_verbose: bool = False
    ollama: OllamaConfig = OllamaConfig()
    gh_token: Optional[SecretStr] = None
    serper_api_key: Optional[SecretStr] = None

    # Nested configurations can be handled here
    # Example:
    # project: Optional[Dict[str, Any]] = None

    @classmethod
    def load_from_json(cls, file_path: Path):
        """Loads and validates settings from a JSON file."""
        if not file_path.exists():
            print(f"⚠️ JSON config file not found at {file_path}, loading defaults.")
            return cls()

        try:
            with open(file_path, "r") as f:
                data = json.load(f)
            return cls(**data)
        except (IOError, json.JSONDecodeError, ValidationError) as e:
            print(f"❌ Error loading config from JSON: {e}")
            return cls()


# Load environment variables (e.g., for GH_TOKEN)
load_dotenv(find_dotenv())

# Load settings from the JSON file first
config = Settings.load_from_json(CONFIG_FILE)

# Now, override with environment variables if they exist
# This is a key benefit of Pydantic and ensures precedence
# (e.g., GH_TOKEN from .env will override a value in config.json)
env_settings = {}
if os.getenv("GH_TOKEN"):
    env_settings["gh_token"] = os.getenv("GH_TOKEN")
if os.getenv("SERPER_API_KEY"):
    env_settings["serper_api_key"] = os.getenv("SERPER_API_KEY")

config = config.model_copy(update=env_settings)

# Example usage
if __name__ == "__main__":
    print("--- Loaded Configuration ---")
    print(f"App Name: {config.app_name}")
    print(f"Agents Verbose: {config.agents_verbose}")
    print(f"Ollama Model: {config.ollama.model}")
    print(f"Ollama Base URL: {config.ollama.base_url}")
    print(f"GitHub Token (masked): {config.gh_token.get_secret_value()[:4]}...")
    if config.serper_api_key:
        print(f"Serper API Key loaded.")
    else:
        print("Serper API Key not found.")
