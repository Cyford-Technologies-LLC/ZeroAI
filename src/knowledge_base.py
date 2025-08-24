"""Knowledge base system for ZeroAI."""

import os
from pathlib import Path
from typing import List, Optional

class KnowledgeBase:
    """Simple knowledge base for company/project information."""
    
    def __init__(self, knowledge_dir: str = "knowledge"):
        self.knowledge_dir = Path(knowledge_dir)
        
    def search(self, query: str) -> Optional[str]:
        """Search knowledge base for relevant information."""
        query_lower = query.lower()
        
        # Check for company-related queries
        if any(term in query_lower for term in ["cyford", "company", "technologies"]):
            return self._load_file("cyford_technologies.md")
            
        return None
    
    def _load_file(self, filename: str) -> Optional[str]:
        """Load content from knowledge file."""
        file_path = self.knowledge_dir / filename
        
        if file_path.exists():
            try:
                with open(file_path, 'r', encoding='utf-8') as f:
                    return f.read()
            except:
                pass
                
        return None

# Global knowledge base
kb = KnowledgeBase()