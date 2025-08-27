# peer_discovery.py

import sys
import os
import requests
import json
import time
from typing import List, Optional, Dict, Any
from rich.console import Console
from threading import Thread, Lock
from dataclasses import dataclass
import psutil

console = Console()
PEERS_YML_PATH = Path("peers.yml")
PEER_DISCOVERY_INTERVAL = 60
PEER_PING_TIMEOUT = 5
PEER_PING_RETRIES = 3

@dataclass
class PeerCapabilities:
    available: bool = False
    models: List[str] = None
    load_avg: float = 0.0
    memory: float = 0.0
    # Assuming metrics service provides these
    gpu_available: bool = False
    gpu_load: float = 0.0

@dataclass
class PeerNode:
    name: str
    ip: str
    capabilities: PeerCapabilities


class PeerDiscovery:
    def __init__(self):
        self.peers: Dict[str, PeerNode] = {}
        self.peers_lock = Lock()
        self.discovery_thread: Optional[Thread] = None

        if not PEERS_YML_PATH.exists():
            console.print("[yellow]Warning: peers.yml not found. Using default peers.[/yellow]")
            self._write_default_peers_yml()

    def _write_default_peers_yml(self):
        with open(PEERS_YML_PATH, "w") as f:
            f.write("peers:\n")
            f.write("  - name: local-node\n")
            f.write("    ip: ollama\n")
            f.write("#  - name: GPU-01\n")
            f.write("#    ip: 149.36.1.65\n")

    def _load_peers_from_yml(self) -> List[Dict[str, str]]:
        try:
            with open(PEERS_YML_PATH, 'r') as f:
                import yaml
                data = yaml.safe_load(f)
                return data.get('peers', [])
        except Exception as e:
            console.print(f"[red]Error loading peers.yml: {e}[/red]")
            return []

    def _get_system_load(self) -> float:
        try:
            return psutil.cpu_percent(interval=1)
        except Exception:
            return 0.0

    def _get_ollama_models(self, ip: str) -> List[str]:
        try:
            ollama_url = f"http://{ip}:11434"
            response = requests.get(f"{ollama_url}/api/tags", timeout=5)
            response.raise_for_status()
            return [m['name'] for m in response.json().get('models', [])]
        except requests.exceptions.RequestException:
            return []

    def _get_ollama_memory(self, ip: str) -> float:
        # Assuming a more robust check now exists
        try:
            # Example using a potential metrics service on a peer
            response = requests.get(f"http://{ip}:8080/health", timeout=5)
            response.raise_for_status()
            return response.json().get('available_memory_gb', 0.0)
        except requests.exceptions.RequestException:
            # Fallback to local check if remote fails
            try:
                return psutil.virtual_memory().available / (1024**3)
            except Exception:
                return 0.0

    def _get_gpu_metrics(self, ip: str) -> Tuple[bool, float]:
        # Placeholder for real GPU metric fetching from a peer
        # Assume a metrics service running on the peer
        try:
            response = requests.get(f"http://{ip}:8080/metrics/gpu", timeout=5)
            response.raise_for_status()
            gpu_data = response.json()
            return gpu_data.get('gpu_available', False), gpu_data.get('gpu_load', 0.0)
        except requests.exceptions.RequestException:
            return False, 0.0


    def _check_ollama_peer(self, peer_name: str, ollama_ip: str) -> PeerCapabilities:
        for attempt in range(PEER_PING_RETRIES):
            try:
                ollama_url = f"http://{ollama_ip}:11434"

                # Use the correct API endpoint: /api/tags
                response = requests.get(f"{ollama_url}/api/tags", timeout=PEER_PING_TIMEOUT)
                response.raise_for_status()
                models = [m['name'] for m in response.json().get('models', [])]

                load = self._get_system_load()
                memory = self._get_ollama_memory(ollama_ip)
                gpu_available, gpu_load = self._get_gpu_metrics(ollama_ip)

                console.print(f"✅ Discovered peer {peer_name} at {ollama_ip} with models: {models}", style="green")
                console.print(f"   Metrics: Load={load:.1f}%, Mem={memory:.1f} GiB, GPU={gpu_available}", style="green")
                return PeerCapabilities(available=True, models=models, load_avg=load, memory=memory, gpu_available=gpu_available, gpu_load=gpu_load)
            except requests.exceptions.RequestException as e:
                console.print(f"❌ Failed to connect to peer {peer_name} at {ollama_ip} (Attempt {attempt + 1}/{PEER_PING_RETRIES}): {e}", style="red")
                time.sleep(1)

        console.print(f"❌ Failed to connect to peer {peer_name} after {PEER_PING_RETRIES} retries.", style="red")
        return PeerCapabilities(available=False)


    def _discovery_cycle(self):
        console.print("\n🔍 Initiating peer discovery cycle...", style="cyan")
        new_peers: Dict[str, PeerNode] = {}
        peers_from_yml = self._load_peers_from_yml()

        for peer_info in peers_from_yml:
            name = peer_info['name']
            ip = peer_info['ip']

            capabilities = self._check_ollama_peer(name, ip)
            new_peers[name] = PeerNode(name, ip, capabilities)

        with self.peers_lock:
            self.peers = new_peers

        console.print("🔍 Peer discovery cycle complete.", style="cyan")

    def _discovery_loop(self):
        while True:
            self._discovery_cycle()
            time.sleep(PEER_DISCOVERY_INTERVAL)

    def start_discovery_service(self):
        if self.discovery_thread is None or not self.discovery_thread.is_alive():
            self.discovery_thread = Thread(target=self._discovery_loop, daemon=True)
            self.discovery_thread.start()

    def get_peers(self) -> List[PeerNode]:
        with self.peers_lock:
            return list(self.peers.values())
