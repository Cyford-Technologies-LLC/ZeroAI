"""Simple caching system for ZeroAI responses."""

import json
import hashlib
from pathlib import Path
from typing import Optional, Dict, Any

class ResponseCache:
    """Cache system for AI responses."""
    
    def __init__(self, cache_dir: str = "cache"):
        self.cache_dir = Path(cache_dir)
        self.cache_dir.mkdir(exist_ok=True)
        
    def _get_cache_key(self, prompt: str, model: str) -> str:
        """Generate cache key from prompt and model."""
        content = f"{prompt}:{model}"
        return hashlib.md5(content.encode()).hexdigest()
    
    def get(self, prompt: str, model: str) -> Optional[str]:
        """Get cached response if exists."""
        cache_key = self._get_cache_key(prompt, model)
        cache_file = self.cache_dir / f"{cache_key}.json"
        
        if cache_file.exists():
            try:
                with open(cache_file, 'r') as f:
                    data = json.load(f)
                    return data.get('response')
            except:
                pass
        return None
    
    def set(self, prompt: str, model: str, response: str) -> None:
        """Cache a response."""
        cache_key = self._get_cache_key(prompt, model)
        cache_file = self.cache_dir / f"{cache_key}.json"
        
        data = {
            'prompt': prompt,
            'model': model,
            'response': response
        }
        
        with open(cache_file, 'w') as f:
            json.dump(data, f, indent=2)

# Global cache instance
cache = ResponseCache()