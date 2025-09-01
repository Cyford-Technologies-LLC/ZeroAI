# src/legacy_config_wrapper.py
from src.config import config
from types import SimpleNamespace

# This provides a compatibility layer for code expecting a 'Settings' object.
# It uses the modern Pydantic config as the source of truth.

class MockModelConfig(SimpleNamespace):
    name = config.model.name
    temperature = config.model.temperature
    max_tokens = config.model.max_tokens
    base_url = config.model.base_url

class MockAgentConfig(SimpleNamespace):
    max_concurrent = config.agents.max_concurrent
    timeout = config.agents.timeout
    verbose = config.agents.verbose

class MockSettings(SimpleNamespace):
    model = MockModelConfig()
    agents = MockAgentConfig()
    logging = config.logging
    cloud = config.cloud

    if hasattr(config, 'zeroai'):
        zeroai = config.zeroai
    if hasattr(config, 'thunder'):
        thunder = config.thunder
    if hasattr(config, 'smart_routing'):
        smart_routing = config.smart_routing

Settings = MockSettings()
