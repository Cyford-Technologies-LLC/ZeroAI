from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import json
import os
from typing import List, Dict, Any
import requests
from datetime import datetime

app = FastAPI()

class PeerModel(BaseModel):
    url: str
    name: str = ""
    enabled: bool = True

PEERS_FILE = "/app/config/peers.json"

def load_peers() -> List[Dict]:
    if os.path.exists(PEERS_FILE):
        with open(PEERS_FILE, 'r') as f:
            return json.load(f)
    return []

def save_peers(peers: List[Dict]):
    os.makedirs(os.path.dirname(PEERS_FILE), exist_ok=True)
    with open(PEERS_FILE, 'w') as f:
        json.dump(peers, f, indent=2)

def check_peer_status(url: str) -> Dict:
    try:
        response = requests.get(f"{url}/health", timeout=2)
        return {
            "status": "online" if response.status_code == 200 else "error",
            "response_time": response.elapsed.total_seconds(),
            "last_check": datetime.now().isoformat()
        }
    except:
        return {
            "status": "offline",
            "response_time": None,
            "last_check": datetime.now().isoformat()
        }

@app.get("/peers")
def get_peers():
    peers = load_peers()
    for peer in peers:
        peer.update(check_peer_status(peer["url"]))
    return {"peers": peers}

@app.post("/peers")
def add_peer(peer: PeerModel):
    peers = load_peers()
    new_peer = {
        "url": peer.url,
        "name": peer.name or peer.url,
        "enabled": peer.enabled,
        "added": datetime.now().isoformat()
    }
    peers.append(new_peer)
    save_peers(peers)
    return {"success": True, "peer": new_peer}

@app.delete("/peers/{peer_index}")
def remove_peer(peer_index: int):
    peers = load_peers()
    if 0 <= peer_index < len(peers):
        removed = peers.pop(peer_index)
        save_peers(peers)
        return {"success": True, "removed": removed}
    raise HTTPException(404, "Peer not found")

@app.put("/peers/{peer_index}")
def update_peer(peer_index: int, peer: PeerModel):
    peers = load_peers()
    if 0 <= peer_index < len(peers):
        peers[peer_index].update({
            "url": peer.url,
            "name": peer.name,
            "enabled": peer.enabled
        })
        save_peers(peers)
        return {"success": True, "peer": peers[peer_index]}
    raise HTTPException(404, "Peer not found")