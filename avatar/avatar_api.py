from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import os
import subprocess
import tempfile
import requests
import base64
from pathlib import Path

app = Flask(__name__)
CORS(app)
OLLAMA_HOST = os.getenv('OLLAMA_HOST', 'http://ollama:11434')

@app.route('/generate', methods=['POST'])
def generate_avatar():
    try:
        data = request.json
        prompt = data.get('prompt', 'Hello')
        
        # Simple test response for now
        return jsonify({
            'status': 'success',
            'message': f'Avatar generation requested for: {prompt}',
            'note': 'This is a test response. Full avatar generation not yet implemented.'
        })
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/analyze', methods=['POST'])
def analyze_image():
    data = request.json
    image_path = data.get('image')
    question = data.get('question', 'What do you see in this image?')
    
    with open(image_path, 'rb') as f:
        image_b64 = base64.b64encode(f.read()).decode()
    
    response = requests.post(f'{OLLAMA_HOST}/api/generate', json={
        'model': 'llava:7b',
        'prompt': question,
        'images': [image_b64],
        'stream': False
    })
    
    return jsonify({'analysis': response.json()['response']})

@app.route('/health')
def health():
    return jsonify({'status': 'ok'})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=7860)