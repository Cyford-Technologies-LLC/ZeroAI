#!/usr/bin/env python3
"""Start ZeroAI Portal with Docker host IP detection."""

import socket
import subprocess
import sys
from pathlib import Path

def get_docker_host_ips():
    """Get Docker host and container IPs."""
    ips = {}
    
    # Get container internal IP
    try:
        hostname = socket.gethostname()
        ips['container'] = socket.gethostbyname(hostname)
    except:
        ips['container'] = "unknown"
    
    # Get Docker host IP (gateway)
    try:
        result = subprocess.run(['ip', 'route', 'show', 'default'], 
                              capture_output=True, text=True, timeout=5)
        if result.returncode == 0:
            gateway = result.stdout.split()[2]
            ips['docker_host'] = gateway
        else:
            ips['docker_host'] = "172.17.0.1"  # Default Docker gateway
    except:
        ips['docker_host'] = "172.17.0.1"
    
    # Standard IPs
    ips['localhost'] = "127.0.0.1"
    ips['all_interfaces'] = "0.0.0.0"
    
    return ips

def start_portal():
    """Start portal with Docker-aware IP display."""
    ips = get_docker_host_ips()
    
    print("=" * 60)
    print("ZeroAI Portal Server (Docker Mode)")
    print("=" * 60)
    
    print("Container Network:")
    print(f"  Container IP: {ips['container']}")
    print(f"  Docker Host:  {ips['docker_host']}")
    
    print("\nAccess URLs:")
    print(f"  Internal:     http://{ips['localhost']}:333")
    print(f"  Container:    http://{ips['container']}:333")
    print(f"  Docker Host:  http://{ips['docker_host']}:333")
    print(f"  External:     http://HOST_IP:333")
    
    print("\nAdmin Login:")
    print("  Username: admin")
    print("  Password: admin")
    
    print("=" * 60)
    
    # Check if running in Docker
    if Path("/.dockerenv").exists():
        print("Running inside Docker container")
        # Initialize database if needed
        if not Path("/app/data/zeroai.db").exists():
            print("Initializing database...")
            subprocess.run([sys.executable, "/app/setup_db.py"])
        
        # Start portal
        subprocess.run([sys.executable, "/app/www/api/portal_api.py"])
    else:
        print("Not in Docker - use start_portal.py instead")

if __name__ == "__main__":
    start_portal()