# /app/src/peer_discovery.py

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

console = Console()
PEERS_CONFIG_PATH = Path("config/peers.json")
PEER_DISCOVERY_INTERVAL = 60
PEER_PING_TIMEOUT = 5
PEER_PING_RETRIES = 3
CACHE_DURATION = 60  # 60 seconds cache

# Debug levels: 0=silent, 1=errors, 2=warnings, 3=info, 4=debug, 5=verbose
DEBUG_LEVEL = int(os.getenv('PEER_DEBUG_LEVEL', '3'))
ENABLE_PEER_LOGGING = os.getenv('ENABLE_PEER_LOGGING', 'true').lower() == 'true'

def log_peer(message: str, level: int = 3, style: str = None):
    """Log peer discovery messages based on debug level"""
    if ENABLE_PEER_LOGGING and level <= DEBUG_LEVEL:
        if style:
            console.print(message, style=style)
        else:
            console.print(message)

@dataclass
class PeerCapabilities:
    available: bool = False
    models: List[str] = None
    load_avg: float = 0.0
    memory: float = 0.0
    gpu_available: bool = False
    gpu_memory: float = 0.0
    cpu_cores: int = 0

@dataclass
class PeerNode:
    name: str
    ip: str
    capabilities: PeerCapabilities


class PeerDiscovery:
    _instance = None
    _initialized = False
    
    def __new__(cls):
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance
    
    def __init__(self):
        if not self._initialized:
            self.peers: Dict[str, PeerNode] = {}
            self.peers_lock = Lock()
            self.discovery_thread: Optional[Thread] = None
            self.cache_timestamp = 0
            self.cached_peers: Dict[str, PeerNode] = {}
            PeerDiscovery._initialized = True

    def _load_peers_from_config(self) -> List[Dict[str, Any]]:
        if not PEERS_CONFIG_PATH.exists():
            log_peer(f"Warning: Configuration file {PEERS_CONFIG_PATH} not found.", 2, "yellow")
            return []

        try:
            with open(PEERS_CONFIG_PATH, 'r') as f:
                data = json.load(f)
                if not isinstance(data, dict) or "peers" not in data or not isinstance(data["peers"], list):
                    log_peer(f"Error: {PEERS_CONFIG_PATH} is not in the correct format.", 1, "red")
                    return []
                return data.get('peers', [])
        except Exception as e:
            log_peer(f"Error loading {PEERS_CONFIG_PATH}: {e}", 1, "red")
            return []


    def _save_peers_to_config(self, peers: Dict[str, PeerNode]):
        """Save peer details with full capabilities to config file"""
        try:
            PEERS_CONFIG_PATH.parent.mkdir(parents=True, exist_ok=True)
            peers_data = []
            for peer in peers.values():
                peer_dict = {
                    "name": peer.name,
                    "ip": peer.ip,
                    "port": 11434,
                    "available": peer.capabilities.available,
                    "models": peer.capabilities.models or [],
                    "load_avg": peer.capabilities.load_avg,
                    "memory_gb": peer.capabilities.memory,
                    "gpu_available": peer.capabilities.gpu_available,
                    "gpu_memory_gb": peer.capabilities.gpu_memory,
                    "cpu_cores": peer.capabilities.cpu_cores,
                    "last_updated": time.time()
                }
                peers_data.append(peer_dict)
            
            with open(PEERS_CONFIG_PATH, 'w') as f:
                json.dump({"peers": peers_data}, f, indent=2)
        except Exception as e:
            log_peer(f"Error saving peers config: {e}", 1, "red")

    def add_peer(self, ip: str, port: int, name: str) -> (bool, str):
        try:
            peers_data = self._load_peers_from_config()
            if any(p['ip'] == ip for p in peers_data):
                return False, f"Peer with IP {ip} already exists."

            new_peer = {"name": name, "ip": ip, "port": port, "available": False, "models": [], "load_avg": 0.0, "memory_gb": 0.0, "gpu_available": False, "gpu_memory_gb": 0.0, "cpu_cores": 0, "last_updated": 0}
            peers_data.append(new_peer)

            with open(PEERS_CONFIG_PATH, 'w') as f:
                json.dump({"peers": peers_data}, f, indent=2)

            self._invalidate_cache()
            return True, f"Successfully added peer {name} at {ip}:{port}."
        except Exception as e:
            return False, f"Failed to add peer: {e}"

    def _load_all_peers(self) -> List[Dict[str, Any]]:
        peers = self._load_peers_from_config()
        if peers:
            return peers
        else:
            return [{"name": "local-node", "ip": "ollama", "port": 11434, "available": False, "models": [], "load_avg": 0.0, "memory_gb": 0.0, "gpu_available": False, "gpu_memory_gb": 0.0, "cpu_cores": 0, "last_updated": 0}]

    def _get_system_load(self) -> float:
        try:
            return psutil.cpu_percent(interval=0.1)  # Faster sampling
        except Exception:
            return 0.0

    def _get_ollama_models(self, ip: str) -> List[str]:
        try:
            ollama_url = f"http://{ip}:11434"
            response = requests.get(f"{ollama_url}/api/tags", timeout=PEER_PING_TIMEOUT)
            response.raise_for_status()
            return [m['name'] for m in response.json().get('models', [])]
        except requests.exceptions.RequestException:
            return []

    def _get_my_capabilities(self) -> PeerCapabilities:
        try:
            ollama_models = self._get_ollama_models("localhost")
            memory_gb = psutil.virtual_memory().available / (1024**3)
            load_avg = psutil.cpu_percent(interval=0.1)
            cpu_cores = psutil.cpu_count(logical=True)
            gpu_available = False
            gpu_memory_gb = 0.0
            return PeerCapabilities(
                available=True,
                models=ollama_models,
                load_avg=load_avg,
                memory=memory_gb,
                gpu_available=gpu_available,
                gpu_memory=gpu_memory_gb,
                cpu_cores=cpu_cores
            )
        except Exception as e:
            log_peer(f"Error getting local capabilities: {e}", 1, "red")
            return PeerCapabilities(available=False)

    def _get_peer_metrics(self, ip: str) -> Optional[Dict[str, Any]]:
        try:
            metrics_url = f"http://{ip}:8080/capabilities"
            response = requests.get(metrics_url, timeout=PEER_PING_TIMEOUT)
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            log_peer(f"⚠️ Failed to get metrics from peer at {ip}: {e}", 4, "yellow")
            return None

    def _check_single_peer(self, peer_info: Dict[str, Any]) -> tuple[str, PeerCapabilities]:
        """Check a single peer's capabilities"""
        peer_name = peer_info['name']
        ollama_ip = peer_info['ip']
        
        if peer_name == "local-node":
            return peer_name, self._get_my_capabilities()
            
        for attempt in range(PEER_PING_RETRIES):
            try:
                ollama_models = self._get_ollama_models(ollama_ip)
                if not ollama_models:
                    continue
                    
                metrics = self._get_peer_metrics(ollama_ip)
                if metrics:
                    capabilities = PeerCapabilities(
                        available=True, models=ollama_models, load_avg=metrics.get('load_avg', 0.0),
                        memory=metrics.get('memory_gb', 0.0), gpu_available=metrics.get('gpu_available', False),
                        gpu_memory=metrics.get('gpu_memory_gb', 0.0), cpu_cores=metrics.get('cpu_cores', 0)
                    )
                else:
                    capabilities = PeerCapabilities(available=True, models=ollama_models)
                    
                return peer_name, capabilities
            except Exception:
                if attempt == PEER_PING_RETRIES - 1:
                    break
                    
        return peer_name, PeerCapabilities(available=False)

    def _invalidate_cache(self):
        """Force cache invalidation"""
        self.cache_timestamp = 0

    def _is_cache_valid(self) -> bool:
        """Check if cache is still valid"""
        return time.time() - self.cache_timestamp < CACHE_DURATION

    def _discovery_cycle(self):
        new_peers: Dict[str, PeerNode] = {}
        peers_to_check = self._load_all_peers()
        
        # Parallel peer checking for better performance
        with ThreadPoolExecutor(max_workers=min(len(peers_to_check), 5)) as executor:
            future_to_peer = {executor.submit(self._check_single_peer, peer_info): peer_info for peer_info in peers_to_check}
            
            for future in as_completed(future_to_peer):
                peer_info = future_to_peer[future]
                try:
                    name, capabilities = future.result(timeout=PEER_PING_TIMEOUT * 2)
                    new_peers[name] = PeerNode(name, peer_info['ip'], capabilities)
                except Exception as e:
                    log_peer(f"❌ {peer_info['name']}: {e}", 2, "red")
                    new_peers[peer_info['name']] = PeerNode(peer_info['name'], peer_info['ip'], PeerCapabilities(available=False))
        
        with self.peers_lock:
            self.peers = new_peers
            self.cached_peers = new_peers.copy()
            self.cache_timestamp = time.time()
        
        self._save_peers_to_config(new_peers)
        available_count = len([p for p in new_peers.values() if p.capabilities.available])
        log_peer(f"🔍 Discovery complete: {available_count}/{len(new_peers)} peers available", 4, "cyan")

    def _discovery_loop(self):
        while True:
            self._discovery_cycle()
            time.sleep(PEER_DISCOVERY_INTERVAL)

    def start_discovery_service(self):
        if self.discovery_thread is None or not self.discovery_thread.is_alive():
            self.discovery_thread = Thread(target=self._discovery_loop, daemon=True)
            self.discovery_thread.start()

    def get_peers(self, force_refresh: bool = False) -> List[PeerNode]:
        """Get peers using cache if valid, otherwise trigger discovery"""
        with self.peers_lock:
            if not force_refresh and self._is_cache_valid() and self.cached_peers:
                log_peer(f"📋 Using cached peers ({len(self.cached_peers)} peers)", 5)
                return list(self.cached_peers.values())
            
            if not self.peers or force_refresh:
                log_peer("🔄 Cache expired, refreshing peer discovery...", 4, "yellow")
                self._discovery_cycle()
            
            return list(self.peers.values())
    
    def get_available_peers(self) -> List[PeerNode]:
        """Get only available peers"""
        return [peer for peer in self.get_peers() if peer.capabilities.available]
    
    @classmethod
    def get_instance(cls):
        """Get singleton instance"""
        return cls()

# Global singleton instance
peer_discovery = PeerDiscovery.get_instance()k}
            
            for future in as_completed(future_to_peer):
                peer_info = future_to_peer[future]
                try:
                    name, capabilities = future.result(timeout=PEER_PING_TIMEOUT * 2)
                    new_peers[name] = PeerNode(name, peer_info['ip'], capabilities)
                except Exception as e:
                    log_peer(f"❌ {peer_info['name']}: {e}", 2, "red")
                    new_peers[peer_info['name']] = PeerNode(peer_info['name'], peer_info['ip'], PeerCapabilities(available=False))
        
        with self.peers_lock:
            self.peers = new_peers
            self.cached_peers = new_peers.copy()
            self.cache_timestamp = time.time()
        
        self._save_peers_to_config(new_peers)
        available_count = len([p for p in new_peers.values() if p.capabilities.available])
        log_peer(f"🔍 Discovery complete: {available_count}/{len(new_peers)} peers available", 4, "cyan")peer_info['name']] = PeerNode(peer_info['name'], peer_info['ip'], PeerCapabilities(available=False))
        
        with self.peers_lock:
            self.peers = new_peers
            self.cached_peers = new_peers.copy()
            self.cache_timestamp = time.time()
        
        self._save_peers_to_config(new_peers)

    def _discovery_loop(self):
        while True:
            self._discovery_cycle()
            time.sleep(PEER_DISCOVERY_INTERVAL)

    def start_discovery_service(self):
        if self.discovery_thread is None or not self.discovery_thread.is_alive():
            self.discovery_thread = Thread(target=self._discovery_loop, daemon=True)
            self.discovery_thread.start()

    def get_peers(self, force_refresh: bool = False) -> List[PeerNode]:
        """Get peers using cache if valid, otherwise trigger discovery"""
        with self.peers_lock:
            if not force_refresh and self._is_cache_valid() and self.cached_peers:
                log_peer(f"📋 Using cached peers ({len(self.cached_peers)} peers)", 5)
                return list(self.cached_peers.values())
            
            if not self.peers or force_refresh:
                log_peer("🔄 Cache expired, refreshing peer discovery...", 4, "yellow")
                self._discovery_cycle()
            
            return list(self.peers.values())
    
    def get_available_peers(self) -> List[PeerNode]:
        """Get only available peers"""
        return [peer for peer in self.get_peers() if peer.capabilities.available]
    
    @classmethod
    def get_instance(cls):
        """Get singleton instance"""
        return cls()

# Global singleton instance
peer_discovery = PeerDiscovery.get_instance()
