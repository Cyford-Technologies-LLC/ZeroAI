from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import os
import subprocess
import tempfile
import requests
import base64
from pathlib import Path
import torch
import numpy as np
from TTS.api import TTS
import cv2

app = Flask(__name__)
CORS(app)
OLLAMA_HOST = os.getenv('OLLAMA_HOST', 'http://ollama:11434')

# Initialize TTS with neural voice
device = "cuda" if torch.cuda.is_available() else "cpu"
tts = TTS("tts_models/en/ljspeech/tacotron2-DDC_ph").to(device)

@app.route('/generate', methods=['POST'])
def generate_avatar():
    try:
        data = request.json
        prompt = data.get('prompt', 'Hello')
        source_image = data.get('image', '/app/default_face.jpg')
        
        # Create temp files
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as audio_file:
            audio_path = audio_file.name
        
        with tempfile.NamedTemporaryFile(suffix='.mp4', delete=False) as video_file:
            video_path = video_file.name
        
        # Generate high-quality TTS
        tts.tts_to_file(text=prompt, file_path=audio_path)
        
        # Use SadTalker for realistic talking face
        generate_talking_face(source_image, audio_path, video_path)
        
        return send_file(video_path, mimetype='video/mp4')
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

def generate_talking_face(image_path, audio_path, output_path):
    """Generate realistic talking face using SadTalker"""
    try:
        # SadTalker command
        cmd = [
            'python', '/app/SadTalker/inference.py',
            '--driven_audio', audio_path,
            '--source_image', image_path,
            '--result_dir', '/tmp/',
            '--still',
            '--preprocess', 'full',
            '--enhancer', 'gfpgan'
        ]
        
        subprocess.run(cmd, check=True, timeout=120)
        
        # Find generated video and copy to output
        result_files = list(Path('/tmp/').glob('*.mp4'))
        if result_files:
            subprocess.run(['cp', str(result_files[0]), output_path], check=True)
        else:
            raise Exception("SadTalker failed to generate video")
            
    except Exception as e:
        # Fallback to basic avatar if SadTalker fails
        create_basic_avatar(audio_path, output_path, "SadTalker failed, using basic avatar")

def create_basic_avatar(audio_path, video_path, text):
    """Fallback basic avatar"""
    fps = 30
    duration = 3
    frames = fps * duration
    
    fourcc = cv2.VideoWriter_fourcc(*'mp4v')
    out = cv2.VideoWriter(video_path, fourcc, fps, (640, 480))
    
    for i in range(frames):
        frame = np.zeros((480, 640, 3), dtype=np.uint8)
        frame.fill(50)
        
        # Draw face
        cv2.circle(frame, (320, 240), 100, (200, 180, 160), -1)
        cv2.circle(frame, (290, 210), 10, (0, 0, 0), -1)
        cv2.circle(frame, (350, 210), 10, (0, 0, 0), -1)
        
        # Animate mouth
        mouth_open = int(10 * abs(np.sin(i * 0.5)))
        cv2.ellipse(frame, (320, 280), (20, mouth_open), 0, 0, 180, (0, 0, 0), -1)
        
        cv2.putText(frame, text[:30], (50, 50), cv2.FONT_HERSHEY_SIMPLEX, 1, (255, 255, 255), 2)
        
        out.write(frame)
    
    out.release()

@app.route('/analyze', methods=['POST'])
def analyze_image():
    try:
        question = request.form.get('question', 'What do you see?')
        return jsonify({'analysis': f'Image analysis: {question}'})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/health')
def health():
    return jsonify({'status': 'ok', 'device': device, 'models': 'TTS + SadTalker'})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=7860)