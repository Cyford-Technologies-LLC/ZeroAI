# /opt/ZeroAI/src/ai_dev_ops_crew.py (partial modification)

# In the class initialization, add:
self.model_used = None
self.peer_used = None
self.base_url = None

# When you get a model from the router, store the information:
def _get_model(self, prompt, role=None):
    # Your existing code to get a model
    
    # Add this after you get the model:
    if hasattr(llm_instance, 'model'):
        self.model_used = llm_instance.model
    if hasattr(llm_instance, 'base_url'):
        self.base_url = llm_instance.base_url
        # Extract peer from base_url
        if self.base_url:
            try:
                peer_ip = self.base_url.split('//')[1].split(':')[0]
                self.peer_used = peer_ip
            except:
                self.peer_used = "unknown"