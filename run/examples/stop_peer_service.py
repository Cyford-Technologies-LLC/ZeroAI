#!/usr/bin/env python3
"""
Stop Peer Service Daemon
"""

import os
import signal
import sys

def stop_daemon():
    """Stop the peer service daemon"""
    import subprocess
    
    try:
        # Kill all processes using port 8080
        result = subprocess.run(['lsof', '-ti:8080'], capture_output=True, text=True)
        if result.returncode == 0 and result.stdout.strip():
            pids = result.stdout.strip().split('\n')
            for pid in pids:
                try:
                    os.kill(int(pid), signal.SIGTERM)
                    print(f"🛑 Killed process {pid}")
                except:
                    pass
        
        # Also try to kill by process name
        subprocess.run(['pkill', '-f', 'peer_service'], capture_output=True)
        
        # Clean up PID file
        try:
            os.remove('/tmp/zeroai_peer.pid')
        except:
            pass
            
        print("🛑 ZeroAI Peer Service stopped")
        
    except Exception as e:
        print(f"❌ Error stopping service: {e}")
        print("Try manually: sudo lsof -ti:8080 | xargs sudo kill -9")

if __name__ == "__main__":
    stop_daemon()