#!/usr/bin/env python3
"""Start ZeroAI Portal in background with full system info."""

import subprocess
import socket
import sys
from pathlib import Path

def get_system_ips():
    """Get all available IP addresses."""
    import socket
    hostname = socket.gethostname()
    local_ip = socket.gethostbyname(hostname)
    
    # Try to get external IP
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        external_ip = s.getsockname()[0]
        s.close()
    except:
        external_ip = local_ip
    
    return {
        "localhost": "127.0.0.1",
        "local": local_ip,
        "external": external_ip,
        "hostname": hostname
    }

def start_portal_background():
    """Start the portal server in background."""
    ips = get_system_ips()
    
    print("=" * 60)
    print("ZeroAI Portal Server Starting...")
    print("=" * 60)
    
    print("Network Information:")
    print(f"   Hostname: {ips['hostname']}")
    print(f"   Localhost: http://{ips['localhost']}:333")
    print(f"   Local IP:  http://{ips['local']}:333")
    if ips['external'] != ips['local']:
        print(f"   External:  http://{ips['external']}:333")
    
    print("\nAdmin Credentials:")
    print("   Username: admin")
    print("   Password: admin")
    
    print("\nAvailable Endpoints:")
    print("   Login Page:    /")
    print("   Admin Portal:  /admin")
    print("   Agents API:    /agents")
    print("   Crews API:     /crews")
    
    print("\nDatabase Status:")
    db_path = Path("data/zeroai.db")
    if db_path.exists():
        print(f"   Database: Connected ({db_path.absolute()})")
    else:
        print("   Database: Not found - Run setup_db.py first")
        return False
    
    print("=" * 60)
    print("Starting server in background...")
    
    # Start server in background
    try:
        process = subprocess.Popen([
            sys.executable, "www/api/portal_api.py"
        ], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        
        print(f"Server started with PID: {process.pid}")
        print("Server running in background...")
        print("Press Ctrl+C to stop")
        
        # Keep script running
        try:
            process.wait()
        except KeyboardInterrupt:
            print("\nStopping server...")
            process.terminate()
            print("Server stopped")
            
    except Exception as e:
        print(f"Failed to start server: {e}")
        return False
    
    return True

if __name__ == "__main__":
    start_portal_background()