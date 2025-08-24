#!/usr/bin/env python3
"""
Start Peer Service as Background Daemon
"""

import sys
import os
import signal
import time
from pathlib import Path

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

def start_daemon():
    """Start peer service as background daemon"""
    
    # Fork to create daemon
    try:
        pid = os.fork()
        if pid > 0:
            # Parent process - save PID and exit
            with open('/tmp/zeroai_peer.pid', 'w') as f:
                f.write(str(pid))
            print(f"🌐 ZeroAI Peer Service started as daemon (PID: {pid})")
            print("📊 Service running on port 8080")
            print("🛑 Stop with: python3 examples/stop_peer_service.py")
            sys.exit(0)
    except OSError:
        print("❌ Failed to fork daemon process")
        sys.exit(1)
    
    # Child process continues as daemon
    os.setsid()
    os.chdir('/')
    os.umask(0)
    
    # Redirect stdout/stderr to log file
    log_file = open('/tmp/zeroai_peer.log', 'a')
    os.dup2(log_file.fileno(), sys.stdout.fileno())
    os.dup2(log_file.fileno(), sys.stderr.fileno())
    
    # Start the actual service
    from flask import Flask, jsonify
    from peer_discovery import PeerDiscovery
    
    app = Flask(__name__)
    peer_discovery = PeerDiscovery()
    
    @app.route('/capabilities')
    def get_capabilities():
        capabilities = peer_discovery._get_my_capabilities()
        return jsonify({
            'cpu_cores': capabilities.cpu_cores,
            'memory_gb': capabilities.memory_gb,
            'gpu_memory_gb': capabilities.gpu_memory_gb,
            'models': capabilities.models,
            'load_avg': capabilities.load_avg,
            'available': capabilities.available,
            'last_seen': capabilities.last_seen
        })
    
    @app.route('/health')
    def health_check():
        return jsonify({'status': 'healthy'})
    
    # Run the service
    app.run(host='0.0.0.0', port=8080, debug=False)

if __name__ == "__main__":
    start_daemon()