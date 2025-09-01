"""Configuration management for the AI crew system."""
# src/config555.py


import os
import yaml
from pathlib import Path
from typing import Dict, Any, Optional
from pydantic import BaseModel, Field
from env_loader import ENV


class ModelConfig(BaseModel):
    """Model configuration settings."""
    name: str = Field(default_factory=lambda: ENV["DEFAULT_MODEL"], description="Model name")
    temperature: float = Field(default=0.7, ge=0.0, le=2.0)
    max_tokens: int = Field(default=4096, gt=0)
    base_url: str = Field(default_factory=lambda: ENV["OLLAMA_BASE_URL"])
    
    def __init__(self, **data):
        super().__init__(**data)
        # Override with .env values if present
        if ENV.get("DEFAULT_MODEL"):
            self.name = ENV["DEFAULT_MODEL"]
        if ENV.get("OLLAMA_BASE_URL"):
            self.base_url = ENV["OLLAMA_BASE_URL"]


class AgentConfig(BaseModel):
    """Agent configuration settings."""
    max_concurrent: int = Field(default_factory=lambda: ENV["MAX_CONCURRENT_AGENTS"], gt=0)
    timeout: int = Field(default_factory=lambda: ENV["AGENT_TIMEOUT"], gt=0)
    verbose: bool = Field(default=True)
    
    def __init__(self, **data):
        super().__init__(**data)
        # Override with .env values if present
        if ENV.get("MAX_CONCURRENT_AGENTS"):
            self.max_concurrent = ENV["MAX_CONCURRENT_AGENTS"]
        if ENV.get("AGENT_TIMEOUT"):
            self.timeout = ENV["AGENT_TIMEOUT"]


class LoggingConfig(BaseModel):
    """Logging configuration settings."""
    level: str = Field(default_factory=lambda: ENV["LOG_LEVEL"])
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

    @classmethod
    def load_from_file(cls, config_path: str = "config/settings.yaml") -> "Config":
        """Load configuration from YAML file."""
        config_file = Path(config_path)
        
        if config_file.exists():
            with open(config_file, 'r', encoding='utf-8') as f:
                config_data = yaml.safe_load(f)
                return cls(**config_data)
        
        # Return default config if file doesn't exist
        return cls()

    def save_to_file(self, config_path: str = "config/settings.yaml") -> None:
        """Save configuration to YAML file."""
        config_file = Path(config_path)
        config_file.parent.mkdir(parents=True, exist_ok=True)
        
        with open(config_file, 'w', encoding='utf-8') as f:
            yaml.dump(self.model_dump(), f, default_flow_style=False)


# Global config instance
config = Config.load_from_file()