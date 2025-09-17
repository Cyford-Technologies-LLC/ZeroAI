from flask import Flask, request, jsonify, send_file
import os
import subprocess
import tempfile
import requests
import base64
from pathlib import Path

app = Flask(__name__)
OLLAMA_HOST = os.getenv('OLLAMA_HOST', 'http://ollama:11434')

@app.route('/generate', methods=['POST'])
def generate_avatar():
    data = request.json
    prompt = data.get('prompt', data.get('text', ''))
    image_path = data.get('image', 'examples/source_image/art_0.png')
    analyze_image = data.get('analyze_image')
    
    # Get AI response
    if analyze_image:
        with open(analyze_image, 'rb') as f:
            image_b64 = base64.b64encode(f.read()).decode()
        
        response = requests.post(f'{OLLAMA_HOST}/api/generate', json={
            'model': 'llava:7b',
            'prompt': prompt,
            'images': [image_b64],
            'stream': False
        })
        text = response.json()['response']
    else:
        response = requests.post(f'{OLLAMA_HOST}/api/generate', json={
            'model': 'mistral:7b',
            'prompt': prompt,
            'stream': False
        })
        text = response.json()['response']
    
    # Generate TTS audio
    audio_file = tempfile.mktemp(suffix='.wav')
    subprocess.run([
        'python', '-c', 
        f"import pyttsx3; engine = pyttsx3.init(); engine.save_to_file('{text}', '{audio_file}'); engine.runAndWait()"
    ])
    
    # Generate avatar video
    output_file = tempfile.mktemp(suffix='.mp4')
    subprocess.run([
        'python', 'inference.py',
        '--driven_audio', audio_file,
        '--source_image', image_path,
        '--result_dir', os.path.dirname(output_file),
        '--still'
    ])
    
    return send_file(output_file, as_attachment=True)

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