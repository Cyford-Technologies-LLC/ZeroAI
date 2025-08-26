import requests
import json as json_lib
from urllib.parse import urljoin
from rich.console import Console

console = Console()

def process_code_generation(base_url: str, prompt: str, model: str):
    """
    Sends a code generation task to the specified peer URL.
    """
    task_data = {
        "type": "code_generation",
        "prompt": prompt,
        "model": model,
    }
    
    # Use urljoin for robust URL construction
    full_url = urljoin(base_url, '/process_task')
    
    try:
        console.print(f"📤 Sending task to peer agent at {full_url}", style="cyan")
        response = requests.post(full_url, json=task_data, timeout=60)
        
        if response.status_code == 200:
            return response.json().get('response', '')
        else:
            console.print(f"❌ Peer returned status code {response.status_code}: {response.text}", style="red")
            return None
            
    except requests.exceptions.RequestException as e:
        console.print(f"❌ Error communicating with peer: {e}", style="red")
        return None
