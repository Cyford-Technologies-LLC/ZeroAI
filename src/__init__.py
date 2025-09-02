"""
ZeroAI - Zero Cost. Zero Cloud. Zero Limits.

A complete framework for building and deploying AI agent workflows
that run entirely on your hardware with intelligent cost optimization.
"""

__version__ = "1.0.0"
__author__ = "ZeroAI Team"
__email__ = "hello@zeroai.dev"

# Global variable to determine which imports to use
import os
CREW_TYPE = os.environ.get("CREW_TYPE", "full")

# For internal crew, avoid importing the full chain
if CREW_TYPE == "internal":
    # Skip importing ZeroAI and other components that cause circular imports
    from .config import Config
    __all__ = ["Config"]
else:
    # Regular imports for full functionality
    from .zeroai import ZeroAI
    from .config import Config
    __all__ = ["ZeroAI", "Config"]