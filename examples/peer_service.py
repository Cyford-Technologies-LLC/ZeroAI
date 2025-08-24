#!/usr/bin/env python3
"""
Peer Service - HTTP service to expose node capabilities
"""

import sys
import os
from pathlib import Path
from flask import Flask, jsonify
import threading

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from peer_discovery import PeerDiscovery

app = Flask(__name__)
peer_discovery = PeerDiscovery()

@app.route('/capabilities')
def get_capabilities():
    """Return current node capabilities"""
    # Refresh capabilities
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
    """Health check endpoint"""
    return jsonify({'status': 'healthy'})

def main():
    print("🌐 Starting ZeroAI Peer Service on port 8080...")
    print("📊 Exposing node capabilities at /capabilities")
    print("❤️  Health check available at /health")
    
    # Start the service
    app.run(host='0.0.0.0', port=8080, debug=False)

if __name__ == "__main__":
    main()