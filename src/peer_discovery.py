import json
import time
import requests
from typing import Dict, List, Optional
from dataclasses import dataclass, asdict
from pathlib import Path
import threading
import psutil
import subprocess

@dataclass
class NodeCapabilities:
    """Node resource capabilities"""
    cpu_cores: int
    memory_gb: float
    gpu_memory_gb: float
    models: List[str]
    load_avg: float
    available: bool
    last_seen: float

@dataclass
class PeerNode:
    """Peer node information"""
    ip: str
    port: int
    name: str
    capabilities: NodeCapabilities

    @property
    def url(self) -> str:
        return f"http://{self.ip}:{self.port}"

class PeerDiscovery:
    """Manages peer discovery and resource sharing"""
    def __init__(self):
        # ... (other init logic)
        self.peers = {}
        self.ollama_host = os.getenv("OLLAMA_HOST", "ollama-prod")

        # Add the local host to the peer list
        self.add_local_host_as_peer()

    def add_local_host_as_peer(self):
        try:
            # Get the hostname
            hostname = socket.gethostname()
            # Get the internal Docker IP
            internal_ip = socket.gethostbyname(hostname)

            # Add the internal IP as a peer
            self.add_peer(ip=internal_ip, port=8080, name=f"{hostname}-internal")

        except Exception as e:
            # Handle error if network lookup fails
            pass

    def get_public_ip(self):
        # This function should be implemented to retrieve the public IP
        # using an external service or a pre-configured environment variable.
        # For example, using an environment variable OLLAMA_PUBLIC_HOST
        return os.getenv("OLLAMA_PUBLIC_HOST")

    def discover_peers(self):
        # ... (existing discovery logic)
        public_ip = self.get_public_ip()
        if public_ip:
            self.add_peer(ip=public_ip, port=8080, name=f"{socket.gethostname()}-public")

    def __init__(self, config_file: str = "config/peers.json"):
        self.config_file = Path(config_file)
        self.peers: Dict[str, PeerNode] = {}
        self.my_capabilities = self._get_my_capabilities()
        self.port = 8080
        self.load_peer_config()

    def _get_my_capabilities(self) -> NodeCapabilities:
        """Get current node capabilities"""
        # Get system info
        cpu_cores = psutil.cpu_count()
        memory_gb = psutil.virtual_memory().total / (1024**3)
        load_avg = psutil.cpu_percent(interval=1)

        # Check GPU memory (if available)
        gpu_memory_gb = 0
        try:
            result = subprocess.run(['nvidia-smi', '--query-gpu=memory.total', '--format=csv,noheader,nounits'],
                                  capture_output=True, text=True)
            if result.returncode == 0:
                gpu_memory_gb = float(result.stdout.strip()) / 1024
        except:
            pass

        # Get available models
        models = self._get_available_models()

        return NodeCapabilities(
            cpu_cores=cpu_cores,
            memory_gb=memory_gb,
            gpu_memory_gb=gpu_memory_gb,
            models=models,
            load_avg=load_avg,
            available=True,
            last_seen=time.time()
        )

    def _get_available_models(self) -> List[str]:
        """Get list of available Ollama models"""
        try:
            result = subprocess.run(['ollama', 'list'], capture_output=True, text=True)
            if result.returncode == 0:
                lines = result.stdout.strip().split('\n')[1:]  # Skip header
                models = [line.split()[0] for line in lines if line.strip()]
                return models
        except:
            pass
        return []

    def load_peer_config(self):
        """Load peer configuration from file"""
        if self.config_file.exists():
            with open(self.config_file, 'r') as f:
                config = json.load(f)
                for peer_data in config.get('peers', []):
                    capabilities = NodeCapabilities(**peer_data['capabilities'])
                    peer = PeerNode(
                        ip=peer_data['ip'],
                        port=peer_data['port'],
                        name=peer_data['name'],
                        capabilities=capabilities
                    )
                    self.peers[peer.ip] = peer

    def save_peer_config(self):
        """Save peer configuration to file"""
        self.config_file.parent.mkdir(parents=True, exist_ok=True)
        config = {
            'peers': [
                {
                    'ip': peer.ip,
                    'port': peer.port,
                    'name': peer.name,
                    'capabilities': asdict(peer.capabilities)
                }
                for peer in self.peers.values()
            ]
        }
        with open(self.config_file, 'w') as f:
            json.dump(config, f, indent=2)

    def discover_peers(self):
        """Discover and update peer information"""
        for peer in list(self.peers.values()):
            try:
                response = requests.get(f"{peer.url}/capabilities", timeout=5)
                if response.status_code == 200:
                    data = response.json()
                    peer.capabilities = NodeCapabilities(**data)
                    peer.capabilities.last_seen = time.time()
                    peer.capabilities.available = True
                else:
                    peer.capabilities.available = False
            except:
                peer.capabilities.available = False

        self.save_peer_config()

    def add_peer(self, ip: str, port: int = 8080, name: str = None):
        """Add a new peer"""
        if not name:
            name = f"node-{ip}"

        # Check if peer already exists
        if ip in self.peers:
            print(f"⚠️  Peer {ip} already exists, updating capabilities...")

        try:
            response = requests.get(f"http://{ip}:{port}/capabilities", timeout=5)
            if response.status_code == 200:
                data = response.json()
                capabilities = NodeCapabilities(**data)
                peer = PeerNode(ip=ip, port=port, name=name, capabilities=capabilities)
                self.peers[ip] = peer
                self.save_peer_config()
                return True
        except:
            pass
        return False

    def get_best_peer(self, model: str = None, min_memory: float = 0) -> Optional[PeerNode]:
        """Get the best available peer for a task"""
        available_peers = [
            peer for peer in self.peers.values()
            if peer.capabilities.available and
               peer.capabilities.memory_gb >= min_memory and
               (not model or model in peer.capabilities.models)
        ]

        if not available_peers:
            return None

        # Sort by load (lower is better) and memory (higher is better)
        best_peer = min(available_peers,
                       key=lambda p: (p.capabilities.load_avg, -p.capabilities.memory_gb))

        return best_peer

    def get_peers(self) -> List[PeerNode]:
        """Returns a list of all active peer nodes."""
        return list(self.peers.values())

    def get_local_node(self, name: str = "local-node") -> PeerNode:
        """Returns a PeerNode object for the local machine."""
        return PeerNode(
            ip="localhost",
            port=self.port,  # Your local service port
            name=name,
            capabilities=self._get_my_capabilities()
        )

    def start_discovery_service(self):
        """Start background peer discovery"""
        def discovery_loop():
            while True:
                self.discover_peers()
                time.sleep(30)  # Check every 30 seconds

        thread = threading.Thread(target=discovery_loop, daemon=True)
        thread.start()

# Global peer discovery instance
peer_discovery = PeerDiscovery()
