#!/usr/bin/env python3
"""
Start Peer Service for Docker Container
"""

import sys
from pathlib import Path
from flask import Flask, jsonify, request
import os
import subprocess
import json as json_lib

# Add the src directory to the Python path
sys.path.insert(0, str(Path(__file__).parent.parent / "src"))

from peer_discovery import PeerDiscovery

app = Flask(__name__)
peer_discovery = PeerDiscovery()

@app.route('/capabilities')
def get_capabilities():
    """Return current node capabilities"""
    # Refresh capabilities
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

        if task_type == 'code_generation':
            prompt = task_data.get('prompt')
            code_prompt = f"""Generate working code for: {prompt}
Requirements:
- Provide ONLY the code, no explanations
- Make it functional and complete
- Use proper syntax and best practices
Code:"""
        elif task_type == 'research':
            topic = task_data.get('topic')
            code_prompt = f"Research and provide key information about: {topic}"
        else:
            return jsonify({'success': False, 'error': 'Unknown task type'})

        ollama_data = {
            'model': model,
            'prompt': code_prompt,
            'stream': False,
            'options': {
                'temperature': task_data.get('temperature', 0.7),
                'num_predict': task_data.get('max_tokens', 512)
            }
        }

        # Use the OLLAMA_HOST from the environment
        ollama_host = os.environ.get('OLLAMA_HOST', 'http://ollama:11434')

        result = subprocess.run(
            ['curl', '-s', '-X', 'POST', f'{ollama_host}/api/generate',
             '-H', 'Content-Type: application/json',
             '-d', json_lib.dumps(ollama_data)],
            capture_output=True, text=True, timeout=60
        )

        if result.returncode == 0:
            response_data = json_lib.loads(result.stdout)
            return jsonify({
                'success': True,
                'response': response_data.get('response', ''),
                'model_used': model
            })
        else:
            return jsonify({'success': False, 'error': f'Ollama processing failed: {result.stderr}'})

    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

if __name__ == "__main__":
    # The debug=False is important for production to disable the debugger and use a more stable server
    app.run(host='0.0.0.0', port=8080, debug=False)
