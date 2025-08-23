"""
ZeroAI - Zero Cost. Zero Cloud. Zero Limits.

A complete framework for building and deploying AI agent workflows
that run entirely on your hardware with intelligent cost optimization.
"""

__version__ = "1.0.0"
__author__ = "ZeroAI Team"
__email__ = "hello@zeroai.dev"

from .zeroai import ZeroAI
from .config import Config

__all__ = ["ZeroAI", "Config"]