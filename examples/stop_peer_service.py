#!/usr/bin/env python3
"""
Stop Peer Service Daemon
"""

import os
import signal
import sys

def stop_daemon():
    """Stop the peer service daemon"""
    try:
        with open('/tmp/zeroai_peer.pid', 'r') as f:
            pid = int(f.read().strip())
        
        # Send SIGTERM to stop the process
        os.kill(pid, signal.SIGTERM)
        
        # Remove PID file
        os.remove('/tmp/zeroai_peer.pid')
        
        print(f"🛑 ZeroAI Peer Service stopped (PID: {pid})")
        
    except FileNotFoundError:
        print("❌ No peer service running (PID file not found)")
    except ProcessLookupError:
        print("❌ Peer service process not found")
        # Clean up stale PID file
        try:
            os.remove('/tmp/zeroai_peer.pid')
        except:
            pass
    except Exception as e:
        print(f"❌ Error stopping service: {e}")

if __name__ == "__main__":
    stop_daemon()