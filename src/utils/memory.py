# src/utils/memory.py
class Memory:
    """Simple memory implementation for agents."""

    def __init__(self, max_items=100):
        self.memories = []
        self.max_items = max_items

    def add(self, memory_item):
        """Add an item to memory."""
        self.memories.append(memory_item)
        # Keep memory size under control
        if len(self.memories) > self.max_items:
            self.memories = self.memories[-self.max_items:]

    def get(self, query=None):
        """Get all memories or filter by a query."""
        if not query:
            return self.memories

        # Simple string matching for filtering
        return [m for m in self.memories if query.lower() in str(m).lower()]

    def clear(self):
        """Clear all memories."""
        self.memories = []