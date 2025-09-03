# Enhanced Peer Discovery with Error Handling
import sys
import os
from pathlib import Path
import requests
import json
import time
from typing import List, Optional, Dict, Any
from rich.console import Console
from threading import Thread, Lock
from dataclasses import dataclass
import psutil
from concurrent.futures import ThreadPoolExecutor, as_completed
import logging
from enum import Enum

console = Console()

class ErrorType(Enum):
    NETWORK_ERROR = "network_error"
    CONFIG_ERROR = "config_error"
    TIMEOUT_ERROR = "timeout_error"
    VALIDATION_ERROR = "validation_error"
    SYSTEM_ERROR = "system_error"

@dataclass
class PeerError:
    error_type: ErrorType
    message: str
    peer_name: str = None
    timestamp: float = None
    
    def __post_init__(self):
        if self.timestamp is None:
            self.timestamp = time.time()

class ErrorHandler:
    def __init__(self):
        self.errors: List[PeerError] = []
        self.error_lock = Lock()
        
    def log_error(self, error: PeerError):
        with self.error_lock:
            self.errors.append(error)
            console.print(f"[red]ERROR[/red] {error.error_type.value}: {error.message}")
    
    def get_recent_errors(self, minutes: int = 5) -> List[PeerError]:
        cutoff = time.time() - (minutes * 60)
        return [e for e in self.errors if e.timestamp > cutoff]

@dataclass
class PeerCapabilities:
    available: bool = False
    models: List[str] = None
    load_avg: float = 0.0
    memory: float = 0.0
    gpu_available: bool = False
    gpu_memory: float = 0.0
    cpu_cores: int = 0
    last_error: Optional[PeerError] = None

@dataclass
class PeerNode:
    name: str
    ip: str
    capabilities: PeerCapabilities

class EnhancedPeerDiscovery:
    def __init__(self):
        self.peers: Dict[str, PeerNode] = {}
        self.peers_lock = Lock()
        self.error_handler = ErrorHandler()
        self.cache_timestamp = 0
        self.cached_peers: Dict[str, PeerNode] = {}
        
    def _safe_load_config(self, config_path: Path) -> List[Dict[str, Any]]:
        """Safely load configuration with error handling"""
        try:
            if not config_path.exists():
                error = PeerError(ErrorType.CONFIG_ERROR, f"Config file {config_path} not found")
                self.error_handler.log_error(error)
                return []
                
            with open(config_path, 'r') as f:
                data = json.load(f)
                
            if not isinstance(data, dict) or "peers" not in data:
                error = PeerError(ErrorType.VALIDATION_ERROR, f"Invalid config format in {config_path}")
                self.error_handler.log_error(error)
                return []
                
            return data.get('peers', [])
            
        except json.JSONDecodeError as e:
            error = PeerError(ErrorType.CONFIG_ERROR, f"JSON decode error: {e}")
            self.error_handler.log_error(error)
            return []
        except Exception as e:
            error = PeerError(ErrorType.SYSTEM_ERROR, f"Unexpected error loading config: {e}")
            self.error_handler.log_error(error)
            return []
    
    def _safe_network_request(self, url: str, timeout: int = 5, peer_name: str = None) -> Optional[requests.Response]:
        """Make network request with comprehensive error handling"""
        try:
            response = requests.get(url, timeout=timeout)
            response.raise_for_status()
            return response
            
        except requests.exceptions.Timeout:
            error = PeerError(ErrorType.TIMEOUT_ERROR, f"Timeout connecting to {url}", peer_name)
            self.error_handler.log_error(error)
            return None
            
        except requests.exceptions.ConnectionError:
            error = PeerError(ErrorType.NETWORK_ERROR, f"Connection failed to {url}", peer_name)
            self.error_handler.log_error(error)
            return None
            
        except requests.exceptions.HTTPError as e:
            error = PeerError(ErrorType.NETWORK_ERROR, f"HTTP error {e.response.status_code}: {url}", peer_name)
            self.error_handler.log_error(error)
            return None
            
        except Exception as e:
            error = PeerError(ErrorType.SYSTEM_ERROR, f"Unexpected network error: {e}", peer_name)
            self.error_handler.log_error(error)
            return None
    
    def _check_peer_with_recovery(self, peer_info: Dict[str, Any]) -> tuple[str, PeerCapabilities]:
        """Check peer with automatic error recovery"""
        peer_name = peer_info['name']
        peer_ip = peer_info['ip']
        
        # Try multiple endpoints for resilience
        endpoints = [
            f"http://{peer_ip}:11434/api/tags",
            f"http://{peer_ip}:8080/capabilities"
        ]
        
        for attempt in range(3):  # 3 retry attempts
            for endpoint in endpoints:
                response = self._safe_network_request(endpoint, peer_name=peer_name)
                if response:
                    try:
                        data = response.json()
                        models = [m['name'] for m in data.get('models', [])]
                        
                        capabilities = PeerCapabilities(
                            available=True,
                            models=models,
                            load_avg=data.get('load_avg', 0.0),
                            memory=data.get('memory_gb', 0.0),
                            gpu_available=data.get('gpu_available', False),
                            gpu_memory=data.get('gpu_memory_gb', 0.0),
                            cpu_cores=data.get('cpu_cores', 0)
                        )
                        return peer_name, capabilities
                        
                    except (json.JSONDecodeError, KeyError) as e:
                        error = PeerError(ErrorType.VALIDATION_ERROR, f"Invalid response format: {e}", peer_name)
                        self.error_handler.log_error(error)
                        continue
            
            # Wait before retry
            if attempt < 2:
                time.sleep(1 * (attempt + 1))  # Exponential backoff
        
        # All attempts failed
        failed_capabilities = PeerCapabilities(available=False)
        failed_capabilities.last_error = PeerError(ErrorType.NETWORK_ERROR, "All connection attempts failed", peer_name)
        return peer_name, failed_capabilities
    
    def get_system_health(self) -> Dict[str, Any]:
        """Get overall system health status"""
        recent_errors = self.error_handler.get_recent_errors()
        available_peers = [p for p in self.peers.values() if p.capabilities.available]
        
        return {
            "total_peers": len(self.peers),
            "available_peers": len(available_peers),
            "recent_errors": len(recent_errors),
            "error_rate": len(recent_errors) / max(len(self.peers), 1),
            "last_discovery": self.cache_timestamp,
            "errors_by_type": {
                error_type.value: len([e for e in recent_errors if e.error_type == error_type])
                for error_type in ErrorType
            }
        }
    
    def recover_from_errors(self):
        """Attempt to recover from recent errors"""
        console.print("[yellow]Attempting error recovery...[/yellow]")
        
        # Clear old errors
        cutoff = time.time() - 300  # 5 minutes
        with self.error_handler.error_lock:
            self.error_handler.errors = [e for e in self.error_handler.errors if e.timestamp > cutoff]
        
        # Force refresh peer discovery
        self._discovery_cycle()
        
        console.print("[green]Recovery attempt completed[/green]")