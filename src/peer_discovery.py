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
    gpu_available: bool = False
    gpu_memory: float = 0.0
    cpu_cores: int = 0  # FIX: Added cpu_cores attribute

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
            response = requests.get(f"{ollama_url}/api/tags", timeout=PEER_PING_TIMEOUT)
            response.raise_for_status()
            return [m['name'] for m in response.json().get('models', [])]
        except requests.exceptions.RequestException:
            return []

    def _get_my_capabilities(self) -> PeerCapabilities:
        """Get the capabilities of the local node."""
        try:
            # Get Ollama models from the local ollama service
            ollama_models = self._get_ollama_models("ollama")

            # Gather system metrics using psutil
            memory_gb = psutil.virtual_memory().available / (1024**3)
            load_avg = psutil.cpu_percent(interval=1)
            cpu_cores = psutil.cpu_count(logical=True) # FIX: Get CPU core count

            # Placeholder for GPU metrics (requires additional setup)
            gpu_available = False
            gpu_memory_gb = 0.0

            return PeerCapabilities(
                available=True,
                models=ollama_models,
                load_avg=load_avg,
                memory=memory_gb,
                gpu_available=gpu_available,
                gpu_memory=gpu_memory_gb,
                cpu_cores=cpu_cores # FIX: Pass cpu_cores to the data class
            )
        except Exception:
            return PeerCapabilities(available=False)

    def _get_peer_metrics(self, ip: str) -> Optional[Dict[str, Any]]:
        """
        Retrieves metrics from the peer's API endpoint (your custom metrics).
        """
        try:
            metrics_url = f"http://{ip}:8080/capabilities" # Using your /capabilities endpoint
            response = requests.get(metrics_url, timeout=PEER_PING_TIMEOUT)
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            console.print(f"❌ Failed to get metrics from peer at {ip}: {e}", style="red")
            return None


    def _check_ollama_peer(self, peer_name: str, ollama_ip: str) -> PeerCapabilities:
        for attempt in range(PEER_PING_RETRIES):
            try:
                # Handle local node separately using _get_my_capabilities
                if peer_name == "local-node":
                    return self._get_my_capabilities()

                # First, check if Ollama is running and get its models
                ollama_models = self._get_ollama_models(ollama_ip)
                if not ollama_models:
                    raise requests.exceptions.RequestException("No models found on Ollama instance.")

                # Next, try to get metrics from the zeroai_peer service
                metrics = self._get_peer_metrics(ollama_ip)

                if metrics:
                    capabilities = PeerCapabilities(
                        available=True,
                        models=ollama_models,
                        load_avg=metrics.get('load_avg', 0.0),
                        memory=metrics.get('memory_gb', 0.0),
                        gpu_available=metrics.get('gpu_available', False),
                        gpu_memory=metrics.get('gpu_memory_gb', 0.0),
                        cpu_cores=metrics.get('cpu_cores', 0) # FIX: Get cpu_cores from metrics
                    )
                    console.print(f"✅ Discovered peer {peer_name} at {ollama_ip} with models: {capabilities.models}", style="green")
                    console.print(f"   Metrics: Load={capabilities.load_avg:.1f}%, Mem={capabilities.memory:.1f} GiB, GPU={capabilities.gpu_available}", style="green")
                    return capabilities

                # Fallback to basic info if metrics service is unavailable
                raise requests.exceptions.RequestException("Metrics service unavailable.")

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
peer_discovery = PeerDiscovery()
