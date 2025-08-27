#!/usr/bin/env python3
"""
Start Peer Service for Docker Container
"""

import sys
from pathlib import Path
from flask import Flask, jsonify, request
import os
import requests
import json as json_lib

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from peer_discovery import PeerDiscovery

app = Flask(__name__)
peer_discovery = PeerDiscovery()

@app.route('/capabilities')
def get_capabilities():
    """Return current node capabilities"""
    capabilities = peer_discovery._get_my_capabilities()
    return jsonify({
        'cpu_cores': capabilities.cpu_cores,
        'memory_gb': capabilities.memory,
        'gpu_memory_gb': capabilities.gpu_memory,
        'models': capabilities.models,
        'load_avg': capabilities.load_avg,
        'available': capabilities.available,
    })

@app.route('/health')
def health_check():
    """Health check endpoint"""
    return jsonify({'status': 'healthy'})

@app.route('/process_task', methods=['POST'])
def process_task():
    """Process AI task from another agent"""
    try:
        task_data = request.get_json()
        task_type = task_data.get('type')
        model = task_data.get('model', 'llama3.1:8b')
        temperature = task_data.get('temperature', 0.7)
        max_tokens = task_data.get('max_tokens', 512)

        if task_type == 'code_generation':
            prompt = task_data.get('prompt')
            llm_prompt = f"""Generate working code for: {prompt}
Requirements:
- Provide ONLY the code, no explanations
- Make it functional and complete
- Use proper syntax and best practices
Code:"""
        elif task_type == 'research':
            topic = task_data.get('topic')
            llm_prompt = f"Research and provide key information about: {topic}"
        else:
            return jsonify({'success': False, 'error': 'Unknown task type'})

        ollama_data = {
            'model': model,
            'prompt': llm_prompt,
            'stream': False,
            'options': {
                'temperature': temperature,
                'num_predict': max_tokens
            }
        }

        ollama_host = os.environ.get('OLLAMA_HOST', 'http://ollama:11434')
        api_url = f'{ollama_host}/api/generate'

        # Make the API call using the requests library
        response = requests.post(
            api_url,
            json=ollama_data,
            headers={'Content-Type': 'application/json'},
            timeout=60
        )
        response.raise_for_status()  # Raise an exception for bad status codes (4xx or 5xx)

        response_data = response.json()
        return jsonify({
            'success': True,
            'response': response_data.get('response', ''),
            'model_used': model
        })

    except requests.exceptions.RequestException as e:
        return jsonify({'success': False, 'error': f'Ollama processing failed: {str(e)}'})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

if __name__ == "__main__":
    app.run(host='0.0.0.0', port=8080, debug=False)
