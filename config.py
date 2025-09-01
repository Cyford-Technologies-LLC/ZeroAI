import json
import os
import re
from pathlib import Path
from typing import Optional, Dict, Any, List

from pydantic import BaseModel, Field, ValidationError, SecretStr
from dotenv import find_dotenv, load_dotenv

# Define the path to the JSON configuration file
CONFIG_FILE = Path("config.json")

# Find and load environment variables from the .env file
load_dotenv(find_dotenv())

# Regular expression to find placeholders like {ENV_VAR}
ENV_VAR_PATTERN = re.compile(r"\{(\w+)\}")


def replace_placeholders(data: Any) -> Any:
    """Recursively replaces environment variable placeholders in a nested data structure."""
    if isinstance(data, str):
        for match in ENV_VAR_PATTERN.finditer(data):
            env_var_name = match.group(1)
            env_var_value = os.getenv(env_var_name)
            if env_var_value:
                data = data.replace(match.group(0), env_var_value)
        return data
    if isinstance(data, dict):
        return {k: replace_placeholders(v) for k, v in data.items()}
    if isinstance(data, list):
        return [replace_placeholders(item) for item in data]
    return data


class OllamaConfig(BaseModel):
    """Configuration for the Ollama LLM service."""
    model: str = "ollama/llama3.1:8b"
    base_url: str = "http://149.36.1.65:11434"


class Settings(BaseModel):
    """Application settings, with Pydantic validation."""
    ZeroAI: Dict[str, Any]
    Company_Details: Dict[str, Any]
    serper_api_key: Optional[SecretStr] = None

    @classmethod
    def load_from_json(cls, file_path: Path):
        """Loads, replaces placeholders, and validates settings from a JSON file."""
        if not file_path.exists():
            print(f"⚠️ JSON config file not found at {file_path}, loading defaults.")
            return cls()

        try:
            with open(file_path, "r") as f:
                data = json.load(f)

            # Replace environment variable placeholders
            data = replace_placeholders(data)

            return cls(**data)
        except (IOError, json.JSONDecodeError, ValidationError) as e:
            print(f"❌ Error loading config from JSON: {e}")
            return cls()


# Load settings from the JSON file first with placeholder replacement
config = Settings.load_from_json(CONFIG_FILE)

# Now, override with environment variables if they exist
env_settings = {}
if os.getenv("SERPER_API_KEY"):
    env_settings["serper_api_key"] = SecretStr(os.getenv("SERPER_API_KEY"))

config = config.model_copy(update=env_settings)

# Example usage
if __name__ == "__main__":
    print("--- Loaded Configuration ---")
    print(f"ZeroAI GIT: {config.ZeroAI['Details']['GIT']}")
    print(f"Company Token Key: {config.Company_Details['Projects']['GIT_TOKEN_KEY']}")
    if config.serper_api_key:
        print(f"Serper API Key loaded: {config.serper_api_key.get_secret_value()[:4]}...")
    else:
        print("Serper API Key not found.")
