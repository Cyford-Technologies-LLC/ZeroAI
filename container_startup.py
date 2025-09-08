#!/usr/bin/env python3
"""Container startup script for ZeroAI - runs inside Docker containers."""

import os
import sys
import subprocess
from pathlib import Path

def setup_container_environment():
    """Setup the container environment."""
    print("=" * 50)
    print("ZeroAI Container Starting...")
    print("=" * 50)
    
    # Check if we're in a container
    if not Path("/.dockerenv").exists():
        print("Not running in Docker container")
        return False
    
    # Set working directory
    os.chdir("/app")
    print(f"Working directory: {os.getcwd()}")
    
    # Initialize database if needed
    db_path = Path("/app/data/zeroai.db")
    if not db_path.exists():
        print("Initializing database...")
        try:
            subprocess.run([sys.executable, "/app/setup_db.py"], check=True)
            print("Database initialized successfully")
        except Exception as e:
            print(f"Database initialization failed: {e}")
    else:
        print("Database already exists")
    
    # Start the portal
    print("Starting ZeroAI Portal...")
    try:
        subprocess.run([sys.executable, "/app/start_portal_docker.py"])
    except KeyboardInterrupt:
        print("\nShutting down...")
    except Exception as e:
        print(f"Portal startup failed: {e}")
        return False
    
    return True

if __name__ == "__main__":
    setup_container_environment()