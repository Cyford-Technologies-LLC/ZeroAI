"""Cloud AI provider integrations for hybrid deployments."""

from typing import Optional, Dict, Any
from crewai import LLM
from ..env_loader import ENV


class CloudProviderManager:
    """Manages connections to various cloud AI providers."""
    
    @staticmethod
    def create_openai_llm(
        api_key: Optional[str] = None,
        model: str = "gpt-4",
        temperature: float = 0.7,
        max_tokens: int = 4096
    ) -> LLM:
        """Create OpenAI LLM connection."""
        return LLM(
            model=f"openai/{model}",
            api_key=api_key or ENV["OPENAI_API_KEY"],
            temperature=temperature,
            max_tokens=max_tokens
        )
    
    @staticmethod
    def create_anthropic_llm(
        api_key: Optional[str] = None,
        model: str = "claude-3-sonnet-20240229",
        temperature: float = 0.7,
        max_tokens: int = 4096
    ) -> LLM:
        """Create Anthropic Claude LLM connection."""
        return LLM(
            model=f"anthropic/{model}",
            api_key=api_key or ENV["ANTHROPIC_API_KEY"],
            temperature=temperature,
            max_tokens=max_tokens
        )
    
    @staticmethod
    def create_azure_llm(
        api_key: Optional[str] = None,
        endpoint: Optional[str] = None,
        model: str = "gpt-4",
        temperature: float = 0.7,
        max_tokens: int = 4096
    ) -> LLM:
        """Create Azure OpenAI LLM connection."""
        return LLM(
            model=f"azure/{model}",
            api_key=api_key or ENV["AZURE_OPENAI_API_KEY"],
            base_url=endpoint or ENV["AZURE_OPENAI_ENDPOINT"],
            temperature=temperature,
            max_tokens=max_tokens
        )
    
    @staticmethod
    def create_google_llm(
        api_key: Optional[str] = None,
        model: str = "gemini-pro",
        temperature: float = 0.7,
        max_tokens: int = 4096
    ) -> LLM:
        """Create Google Gemini LLM connection."""
        return LLM(
            model=f"google/{model}",
            api_key=api_key or ENV["GOOGLE_API_KEY"],
            temperature=temperature,
            max_tokens=max_tokens
        )