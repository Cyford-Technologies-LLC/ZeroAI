#!/usr/bin/env python3
"""Test script to verify logging levels work correctly"""

import os
import sys
sys.path.append('src')

# Test different logging levels
test_configs = [
    {"PEER_DEBUG_LEVEL": "1", "ROUTER_DEBUG_LEVEL": "1", "desc": "Errors Only"},
    {"PEER_DEBUG_LEVEL": "3", "ROUTER_DEBUG_LEVEL": "3", "desc": "Normal (Info)"},
    {"PEER_DEBUG_LEVEL": "5", "ROUTER_DEBUG_LEVEL": "5", "desc": "Verbose"},
    {"ENABLE_PEER_LOGGING": "false", "ENABLE_ROUTER_LOGGING": "false", "desc": "Silent"}
]

for config in test_configs:
    print(f"\n=== Testing {config['desc']} ===")
    
    # Set environment variables
    for key, value in config.items():
        if key != 'desc':
            os.environ[key] = value
    
    # Import after setting env vars
    from src.peer_discovery import PeerDiscovery
    from src.distributed_router import DistributedRouter
    
    # Test peer discovery
    peer_discovery = PeerDiscovery.get_instance()
    peers = peer_discovery.get_peers()
    
    # Test router
    router = DistributedRouter(peer_discovery)
    try:
        router.get_optimal_endpoint_and_model("test coding task")
    except:
        pass  # Expected to fail in test
    
    # Clean up modules for next test
    if 'src.peer_discovery' in sys.modules:
        del sys.modules['src.peer_discovery']
    if 'src.distributed_router' in sys.modules:
        del sys.modules['src.distributed_router']