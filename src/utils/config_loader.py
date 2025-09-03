import yaml
from pathlib import Path
from typing import Dict, Any

def load_internal_config() -> Dict[str, Any]:
    """Load internal configuration settings."""
    config_path = Path("config/settings.yaml")
    
    if not config_path.exists():
        return get_default_internal_config()
        
    try:
        with open(config_path, 'r') as f:
            config_data = yaml.safe_load(f)
            
        return config_data.get('internal', get_default_internal_config())
        
    except Exception:
        return get_default_internal_config()

def get_default_internal_config() -> Dict[str, Any]:
    """Get default internal configuration."""
    return {
        "persistent_crews": {
            "enabled": True,
            "auto_start": True,
            "max_queue_size": 100,
            "idle_timeout": 3600,
            "default_projects": ["zeroai", "testcorp"]
        },
        "interactive_mode": {
            "enabled": True,
            "graceful_shutdown": True,
            "status_updates": True
        },
        "resource_management": {
            "memory_limit": "2GB",
            "cpu_limit": 2,
            "cleanup_interval": 300
        }
    }

def is_persistent_crews_enabled() -> bool:
    """Check if persistent crews are enabled."""
    config = load_internal_config()
    return config.get("persistent_crews", {}).get("enabled", True)