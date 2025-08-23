"""
Prime Intellect Instance Manager
Manual pause/resume helper for cost optimization
"""

import requests
import time
from datetime import datetime

class PrimeInstanceManager:
    def __init__(self, bridge_url="http://87.197.100.115:44038"):
        self.bridge_url = bridge_url
        
    def check_health(self):
        """Check if GPU bridge is responding"""
        try:
            response = requests.get(f"{self.bridge_url}/health", timeout=5)
            return response.status_code == 200
        except:
            return False
    
    def get_status(self):
        """Get current instance status"""
        if self.check_health():
            return "RUNNING"
        else:
            return "PAUSED/STOPPED"
    
    def wait_for_resume(self, max_wait=300):
        """Wait for instance to come online after manual resume"""
        print("⏳ Waiting for instance to come online...")
        start_time = time.time()
        
        while time.time() - start_time < max_wait:
            if self.check_health():
                print("✅ Instance is online!")
                return True
            time.sleep(10)
        
        print("❌ Instance did not come online within timeout")
        return False
    
    def cost_estimate(self, running_hours, paused_hours=0):
        """Estimate cost for running and paused hours"""
        return (running_hours * 0.16) + (paused_hours * 0.07)
    
    def usage_reminder(self):
        """Remind user about manual pause"""
        print(f"""
🔔 Prime Intellect Instance Management:

Current Status: {self.get_status()}
Cost Rate: $0.16/hour

💡 Cost Optimization:
1. Keep paused when not using GPU ($0.07/hour)
2. Resume only for GPU processing ($0.16/hour)
3. Use local ZeroAI for simple tasks (free)

📊 Cost Examples:
- Running 1 hour: ${self.cost_estimate(1, 0):.2f}
- Paused 24 hours: ${self.cost_estimate(0, 24):.2f}
- Mixed (2h running + 22h paused): ${self.cost_estimate(2, 22):.2f}
- Always running 24h: ${self.cost_estimate(24, 0):.2f}
        """)

if __name__ == "__main__":
    manager = PrimeInstanceManager()
    manager.usage_reminder()