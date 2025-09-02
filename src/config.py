"""Configuration management for the AI crew system."""
# src/config.py

import os
import yaml
from pathlib import Path
from typing import Dict, Any, Optional
from pydantic import BaseModel, Field, SecretStr
from env_loader import ENV
from dotenv import find_dotenv, load_dotenv

# Find and load environment variables from .env
load_dotenv(find_dotenv())

class ModelConfig(BaseModel):
    """Model configuration settings."""
    name: str = Field(default_factory=lambda: ENV.get("DEFAULT_MODEL"), description="Model name")
    temperature: float = Field(default=0.7, ge=0.0, le=2.0)
    max_tokens: int = Field(default=4096, gt=0)
    base_url: str = Field(default_factory=lambda: ENV.get("OLLAMA_BASE_URL"))

    def __init__(self, **data):
        super().__init__(**data)
        # Override with .env values if present
        if ENV.get("DEFAULT_MODEL"):
            self.name = ENV["DEFAULT_MODEL"]
        if ENV.get("OLLAMA_BASE_URL"):
            self.base_url = ENV["OLLAMA_BASE_URL"]


class AgentConfig(BaseModel):
    """Agent configuration settings."""
    max_concurrent: int = Field(default_factory=lambda: int(ENV.get("MAX_CONCURRENT_AGENTS", 3)), gt=0)
    timeout: int = Field(default_factory=lambda: int(ENV.get("AGENT_TIMEOUT", 300)), gt=0)
    verbose: bool = Field(default=True)

    def __init__(self, **data):
        super().__init__(**data)
        # Override with .env values if present
        if ENV.get("MAX_CONCURRENT_AGENTS"):
            self.max_concurrent = int(ENV["MAX_CONCURRENT_AGENTS"])
        if ENV.get("AGENT_TIMEOUT"):
            self.timeout = int(ENV["AGENT_TIMEOUT"])


class LoggingConfig(BaseModel):
    """Logging configuration settings."""
    level: str = Field(default_factory=lambda: ENV.get("LOG_LEVEL"), description="Logging level")
    file: Optional[str] = Field(default="logs/ai_crew.log")
    format: str = Field(default="%(asctime)s - %(name)s - %(levelname)s - %(message)s")

    def __init__(self, **data):
        super().__init__(**data)
        # Override with .env values if present
        if ENV.get("LOG_LEVEL"):
            self.level = ENV["LOG_LEVEL"]


class CloudConfig(BaseModel):
    """Cloud provider configuration."""
    provider: str = Field(default="local", description="AI provider: local, openai, anthropic, azure, google")
    openai_api_key: Optional[str] = Field(default=None)
    anthropic_api_key: Optional[str] = Field(default=None)
    azure_api_key: Optional[str] = Field(default=None)
    azure_endpoint: Optional[str] = Field(default=None)
    google_api_key: Optional[str] = Field(default=None)


class Config(BaseModel):
    """Main configuration class."""
    model: ModelConfig = Field(default_factory=ModelConfig)
    agents: AgentConfig = Field(default_factory=AgentConfig)
    logging: LoggingConfig = Field(default_factory=LoggingConfig)
    cloud: CloudConfig = Field(default_factory=CloudConfig)

    # Add the new fields
    ZeroAI: Optional[Dict[str, Any]] = None
    Company_Details: Optional[Dict[str, Any]] = None
    github_tokens: Optional[Dict[str, SecretStr]] = Field(default_factory=lambda: {})
    serper_api_key: Optional[SecretStr] = Field(default_factory=lambda: SecretStr(os.getenv("SERPER_API_KEY")) if os.getenv("SERPER_API_KEY") else None)


    @classmethod
    def load_from_file(cls, config_path: str = "config/settings.yaml") -> "Config":
        """Load configuration from YAML file and override with environment variables."""
        config_file = Path(config_path)
        config_data = {}

        if config_file.exists():
            with open(config_file, 'r', encoding='utf-8') as f:
                config_data.update(yaml.safe_load(f) or {})

        # Dynamically load all GitHub tokens from environment variables
        if "github_tokens" not in config_data:
            config_data["github_tokens"] = {}
        
        # Find all environment variables that match GitHub token patterns
        for env_key, env_value in os.environ.items():
            if env_value and (env_key.startswith("GITHUB_TOKEN") or env_key.startswith("GH_TOKEN")):
                # Convert env key to a clean token key
                if env_key.startswith("GITHUB_TOKEN_"):
                    token_key = env_key[14:].lower()  # Remove "GITHUB_TOKEN_" prefix
                elif env_key == "GITHUB_TOKEN":
                    token_key = "general"
                elif env_key.startswith("GH_TOKEN_"):
                    token_key = env_key[9:].lower()  # Remove "GH_TOKEN_" prefix
                else:
                    token_key = env_key.lower()
                
                config_data["github_tokens"][token_key] = env_value

        return cls(**config_data)

    def save_to_file(self, config_path: str = "config/settings.yaml") -> None:
        """Save configuration to YAML file."""
        config_file = Path(config_path)
        config_file.parent.mkdir(parents=True, exist_ok=True)

        with open(config_file, 'w', encoding='utf-8') as f:
            yaml.dump(self.model_dump(), f, default_flow_style=False)

# Global config instance
config = Config.load_from_file()
