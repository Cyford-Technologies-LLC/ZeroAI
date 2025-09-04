"""Environment variable loader for the AI crew system."""

import os
from pathlib import Path
from dotenv import load_dotenv

# Load environment variables from .env file
# Try multiple paths to find .env file
env_paths = [
    Path(".env"),  # Current directory
    Path("../.env"),  # Parent directory
    Path("/opt/ZeroAI/.env")  # Absolute path
]

for env_path in env_paths:
    if env_path.exists():
        load_dotenv(env_path)
        break

# Environment variable getters with defaults
def get_env(key: str, default: str = "") -> str:
    """Get environment variable with optional default."""
    return os.getenv(key, default)

def get_env_bool(key: str, default: bool = False) -> bool:
    """Get boolean environment variable."""
    value = os.getenv(key, str(default)).lower()
    return value in ("true", "1", "yes", "on")

def get_env_int(key: str, default: int = 0) -> int:
    """Get integer environment variable."""
    try:
        return int(os.getenv(key, str(default)))
    except ValueError:
        return default

# Load all environment variables
ENV = {
    # Local AI
    "OLLAMA_BASE_URL": get_env("OLLAMA_BASE_URL", "http://localhost:11434"),
    "DEFAULT_MODEL": get_env("DEFAULT_MODEL", "mistral-nemo"),
    
    # GPU Cloud Providers
    "GPU_ACCESS_ENABLED": get_env("GPU_ACCESS_ENABLED", "false"),
    "PRIME_ENABLED": get_env("PRIME_ENABLED", "false"),
    "PRIME_GPU_BRIDGE_URL": get_env("PRIME_GPU_BRIDGE_URL"),
    
    # Cloud providers
    "OPENAI_API_KEY": get_env("OPENAI_API_KEY"),
    "ANTHROPIC_API_KEY": get_env("ANTHROPIC_API_KEY"),
    "AZURE_OPENAI_API_KEY": get_env("AZURE_OPENAI_API_KEY"),
    "AZURE_OPENAI_ENDPOINT": get_env("AZURE_OPENAI_ENDPOINT"),
    "GOOGLE_API_KEY": get_env("GOOGLE_API_KEY"),
    
    # Application settings
    "LOG_LEVEL": get_env("LOG_LEVEL", "INFO"),
    "MAX_CONCURRENT_AGENTS": get_env_int("MAX_CONCURRENT_AGENTS", 3),
    "AGENT_TIMEOUT": get_env_int("AGENT_TIMEOUT", 300),
}