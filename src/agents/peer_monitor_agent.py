#!/usr/bin/env python3
"""
Peer Monitor Agent - Runs peer status checks in background
"""

import json
import time
import socket
import threading
from pathlib import Path
from datetime import datetime

class PeerMonitorAgent:
    def __init__(self):
        self.peers_file = "/app/config/peers.json"
        self.cache_file = "/app/data/peer_status_cache.json"
        self.running = False
        
    def check_peer_status(self, ip, port, timeout=1):
        """Check if peer is online"""
        try:
            sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            sock.settimeout(timeout)
            result = sock.connect_ex((ip, port))
            sock.close()
            return result == 0
        except:
            return False
    
    def update_peer_status(self):
        """Update status for all peers"""
        try:
            with open(self.peers_file, 'r') as f:
                peers_data = json.load(f)
            
            for peer in peers_data['peers']:
                peer['status'] = 'online' if self.check_peer_status(peer['ip'], peer['port']) else 'offline'
                peer['last_check'] = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            
            # Cache results for fast API access
            with open(self.cache_file, 'w') as f:
                json.dump(peers_data, f)
                
            print(f"Peer status updated: {datetime.now()}")
            
        except Exception as e:
            print(f"Error updating peer status: {e}")
    
    def start_monitoring(self, interval=30):
        """Start background monitoring"""
        self.running = True
        
        def monitor_loop():
            while self.running:
                self.update_peer_status()
                time.sleep(interval)
        
        thread = threading.Thread(target=monitor_loop, daemon=True)
        thread.start()
        print(f"Peer monitoring started (interval: {interval}s)")
    
    def stop_monitoring(self):
        """Stop monitoring"""
        self.running = False
        print("Peer monitoring stopped")

if __name__ == "__main__":
    agent = PeerMonitorAgent()
    agent.start_monitoring()
    
    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        agent.stop_monitoring()