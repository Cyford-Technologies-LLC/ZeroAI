# /app/src/peer_discovery.py

import sys
import os
from pathlib import Path
import requests
import json
import yaml
import time
from typing import List, Optional, Dict, Any
from rich.console import Console
from threading import Thread, Lock
from dataclasses import dataclass
import psutil

console = Console()
PEERS_CONFIG_PATH = Path("/app/config/peers.json")
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
    cpu_cores: int = 0

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

    def _load_peers_from_config(self) -> List[Dict[str, str]]:
        if not PEERS_CONFIG_PATH.exists():
            console.print(f"[yellow]Warning: Configuration file {PEERS_CONFIG_PATH} not found.[/yellow]")
            return []

        try:
            with open(PEERS_CONFIG_PATH, 'r') as f:
                data = json.load(f)
                if not isinstance(data, dict) or "peers" not in data or not isinstance(data["peers"], list):
                    console.print(f"[red]Error: {PEERS_CONFIG_PATH} is not in the correct format.[/red]")
                    return []
                return data.get('peers', [])
        except Exception as e:
            console.print(f"[red]Error loading {PEERS_CONFIG_PATH}: {e}[/red]")
            return []


    def add_peer(self, ip: str, port: int, name: str) -> (bool, str):
        try:
            # Read existing peers or initialize if the file doesn't exist
            peers_data = self._load_peers_from_config()

            # Check if peer already exists to avoid duplicates
            if any(p['ip'] == ip for p in peers_data):
                return False, f"Peer with IP {ip} already exists."

            # Add the new peer
            new_peer = {"name": name, "ip": ip, "port": port}
            peers_data.append(new_peer)

            # Write the updated peer list back to the file
            with open(PEERS_CONFIG_PATH, 'w') as f:
                json.dump({"peers": peers_data}, f, indent=4)

            # Immediately trigger a discovery cycle to pick up the new peer
            self._discovery_cycle()

            return True, f"Successfully added peer {name} at {ip}:{port}."
        except Exception as e:
            return False, f"Failed to add peer: {e}"

    def _load_all_peers(self) -> List[Dict[str, str]]:
        peers = self._load_peers_from_config()
        if peers:
            console.print(f"[green]✅ Loaded {len(peers)} peers from configuration.[/green]")
            console.print("   Loaded peers:", peers) # ADD THIS LINE FOR DEBUGGING
            return peers
        else:
            console.print("[yellow]Warning: No peers configured. Using default local-node.[/yellow]")
            return [{"name": "local-node", "ip": "ollama"}]

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
        try:
            ollama_models = self._get_ollama_models("ollama")
            memory_gb = psutil.virtual_memory().available / (1024**3)
            load_avg = psutil.cpu_percent(interval=1)
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
            console.print(f"[red]Error getting local capabilities: {e}[/red]")
            return PeerCapabilities(available=False)

    def _get_peer_metrics(self, ip: str) -> Optional[Dict[str, Any]]:
        try:
            metrics_url = f"http://{ip}:8080/capabilities"
            response = requests.get(metrics_url, timeout=PEER_PING_TIMEOUT)
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            console.print(f"⚠️ Failed to get metrics from peer at {ip}: {e}", style="yellow")
            return None

    def _check_ollama_peer(self, peer_name: str, ollama_ip: str) -> PeerCapabilities:
        for attempt in range(PEER_PING_RETRIES):
            try:
                if peer_name == "local-node":
                    return self._get_my_capabilities()
                ollama_models = self._get_ollama_models(ollama_ip)
                if not ollama_models:
                    raise requests.exceptions.RequestException("No models found on Ollama instance.")
                console.print(f"✅ Discovered Ollama on peer {peer_name} at {ollama_ip}. Models: {ollama_models}", style="green")
                metrics = self._get_peer_metrics(ollama_ip)
                if metrics:
                    return PeerCapabilities(
                        available=True, models=ollama_models, load_avg=metrics.get('load_avg', 0.0),
                        memory=metrics.get('memory_gb', 0.0), gpu_available=metrics.get('gpu_available', False),
                        gpu_memory=metrics.get('gpu_memory_gb', 0.0), cpu_cores=metrics.get('cpu_cores', 0)
                    )
                else:
                    console.print(f"⚠️  Metrics service failed for peer {peer_name}. Falling back to basic metrics.", style="yellow")
                    return PeerCapabilities(available=True, models=ollama_models)
            except requests.exceptions.RequestException as e:
                console.print(f"❌ Failed to connect to peer {peer_name} at {ollama_ip} (Attempt {attempt + 1}/{PEER_PING_RETRIES}): {e}", style="red")
                time.sleep(0)
        console.print(f"❌ Failed to connect to peer {peer_name} after {PEER_PING_RETRIES} retries. Marking unavailable.", style="red")
        return PeerCapabilities(available=False)

    def _discovery_cycle(self):
        console.print("\n🔍 Initiating peer discovery cycle...", style="cyan")
        new_peers: Dict[str, PeerNode] = {}
        peers_to_check = self._load_all_peers()
        for peer_info in peers_to_check:
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
