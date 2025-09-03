"""
Secure Environment Variable Loader
Checks system-wide config first, then local config
"""
import os
from pathlib import Path
from dotenv import load_dotenv


def load_secure_env():
    """
    Load environment variables with priority:
    1. /etc/cyford/zeroai/.env (system-wide, secure)
    2. .env (local project)
    3. Environment variables
    """
    # Priority 1: System-wide secure config
    system_env = Path("/etc/cyford/zeroai/.env")
    if system_env.exists():
        print(f"✅ Loading secure config from {system_env}")
        load_dotenv(system_env, override=True)
        return "system"
    
    # Priority 2: Local project config
    local_env = Path(".env")
    if local_env.exists():
        print(f"⚠️ Loading local config from {local_env}")
        load_dotenv(local_env, override=False)  # Don't override system vars
        return "local"
    
    print("ℹ️ Using environment variables only")
    return "env_only"


def get_secure_token(token_key: str) -> str:
    """
    Get token with secure fallback chain
    """
    # Try environment first (highest priority)
    token = os.getenv(token_key)
    if token:
        return token
    
    # Load secure env if not already loaded
    load_secure_env()
    
    # Try again after loading
    token = os.getenv(token_key)
    if token:
        return token
    
    print(f"⚠️ Token {token_key} not found in any configuration")
    return None