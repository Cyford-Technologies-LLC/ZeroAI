"""RunPod provider integration for ZeroAI."""

import requests
import time
from typing import Optional, Dict, Any
from crewai import LLM
from ..env_loader import ENV
from rich.console import Console

console = Console()


class RunPodProvider:
    """Manages RunPod GPU instances for ZeroAI processing."""
    
    def __init__(self):
        self.api_key = ENV.get("RUNPOD_API_KEY")
        self.endpoint = ENV.get("RUNPOD_ENDPOINT", "https://api.runpod.ai/v2")
        self.pod_id = ENV.get("RUNPOD_POD_ID")
        self.instance_endpoint = None
        
    def is_enabled(self) -> bool:
        """Check if RunPod is enabled."""
        return ENV.get("RUNPOD_ENABLED", "false").lower() == "true"
    
    def should_auto_start(self) -> bool:
        """Check if auto-start is enabled."""
        return ENV.get("RUNPOD_AUTO_START", "false").lower() == "true"
    
    def is_available(self) -> bool:
        """Check if RunPod is available and accessible."""
        if not self.is_enabled() or not self.api_key:
            return False
            
        try:
            headers = {"Authorization": f"Bearer {self.api_key}"}
            response = requests.get(
                f"{self.endpoint}/pods",
                headers=headers,
                timeout=10
            )
            return response.status_code == 200
        except Exception as e:
            console.print(f"⚠️  RunPod unavailable: {e}", style="yellow")
            return False
    
    def start_pod(self) -> bool:
        """Start RunPod instance."""
        if not self.api_key:
            console.print("❌ RunPod API key not configured", style="red")
            return False
            
        try:
            console.print("🚀 Starting RunPod instance...", style="yellow")
            
            headers = {"Authorization": f"Bearer {self.api_key}"}
            
            # If pod_id exists, resume it, otherwise create new
            if self.pod_id:
                response = requests.post(
                    f"{self.endpoint}/pods/{self.pod_id}/resume",
                    headers=headers,
                    timeout=30
                )
            else:
                # Create new pod
                payload = {
                    "name": "zeroai-instance",
                    "imageName": "runpod/pytorch:2.0.1-py3.10-cuda11.8.0-devel-ubuntu22.04",
                    "gpuTypeId": "NVIDIA RTX 3070",
                    "cloudType": "SECURE",
                    "volumeInGb": 50,
                    "containerDiskInGb": 50,
                    "minVcpuCount": 8,
                    "minMemoryInGb": 32,
                    "dockerArgs": "",
                    "ports": "11434/http",
                    "volumeMountPath": "/workspace"
                }
                
                response = requests.post(
                    f"{self.endpoint}/pods",
                    headers=headers,
                    json=payload,
                    timeout=30
                )
            
            if response.status_code in [200, 201]:
                data = response.json()
                if not self.pod_id:
                    self.pod_id = data.get("id")
                    ENV["RUNPOD_POD_ID"] = self.pod_id
                
                # Wait for pod to be ready
                self._wait_for_pod_ready()
                console.print("✅ RunPod instance started", style="green")
                return True
            else:
                console.print(f"❌ Failed to start pod: {response.status_code}", style="red")
                return False
                
        except Exception as e:
            console.print(f"❌ RunPod error: {e}", style="red")
            return False
    
    def stop_pod(self) -> bool:
        """Stop RunPod instance."""
        if not self.pod_id or not self.api_key:
            return False
            
        try:
            console.print("⏹️  Stopping RunPod instance...", style="yellow")
            
            headers = {"Authorization": f"Bearer {self.api_key}"}
            response = requests.post(
                f"{self.endpoint}/pods/{self.pod_id}/stop",
                headers=headers,
                timeout=30
            )
            
            if response.status_code == 200:
                console.print("✅ RunPod instance stopped", style="green")
                return True
            else:
                console.print(f"❌ Failed to stop pod: {response.status_code}", style="red")
                return False
                
        except Exception as e:
            console.print(f"❌ RunPod error: {e}", style="red")
            return False
    
    def _wait_for_pod_ready(self, max_wait: int = 300) -> bool:
        """Wait for pod to be ready."""
        console.print("⏳ Waiting for RunPod to be ready...", style="yellow")
        
        for i in range(max_wait):
            if self._check_pod_status():
                return True
            time.sleep(1)
            
        console.print("❌ RunPod startup timeout", style="red")
        return False
    
    def _check_pod_status(self) -> bool:
        """Check if pod is ready."""
        try:
            headers = {"Authorization": f"Bearer {self.api_key}"}
            response = requests.get(
                f"{self.endpoint}/pods/{self.pod_id}",
                headers=headers,
                timeout=5
            )
            
            if response.status_code == 200:
                data = response.json()
                status = data.get("desiredStatus")
                runtime_status = data.get("runtime", {}).get("uptimeInSeconds", 0)
                
                # Pod is ready if running and has been up for at least 30 seconds
                if status == "RUNNING" and runtime_status > 30:
                    self.instance_endpoint = f"https://{self.pod_id}-11434.proxy.runpod.net"
                    return True
            
            return False
        except:
            return False
    
    def get_instance_endpoint(self) -> str:
        """Get the endpoint URL for the RunPod instance."""
        if not self.instance_endpoint and self.pod_id:
            self.instance_endpoint = f"https://{self.pod_id}-11434.proxy.runpod.net"
        return self.instance_endpoint or "http://ollama:11434"
    
    def create_runpod_llm(
        self,
        model: str = "llama3.1:8b",
        temperature: float = 0.7,
        max_tokens: int = 4096
    ) -> Optional[LLM]:
        """Create LLM connection to RunPod instance."""
        if not self.is_enabled():
            return None
            
        try:
            # Start pod if auto-start is enabled and no active pod
            if self.should_auto_start() and not self._check_pod_status():
                if not self.start_pod():
                    return None
            
            return LLM(
                model=f"ollama/{model}",
                base_url=self.get_instance_endpoint(),
                temperature=temperature,
                max_tokens=max_tokens
            )
        except Exception as e:
            console.print(f"❌ Failed to create RunPod LLM: {e}", style="red")
            return None
    
    def get_status(self) -> Dict[str, Any]:
        """Get RunPod provider status."""
        pod_running = self._check_pod_status() if self.pod_id else False
        
        return {
            "enabled": self.is_enabled(),
            "available": self.is_available(),
            "auto_start": self.should_auto_start(),
            "pod_active": pod_running,
            "pod_id": self.pod_id,
            "endpoint": self.get_instance_endpoint() if pod_running else None,
            "cost_per_hour": "$0.16"  # RTX 3070 pricing
        }