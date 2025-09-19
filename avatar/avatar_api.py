from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import os
import subprocess
import tempfile
import requests
import base64
from pathlib import Path
import cv2
import numpy as np
from TTS.api import TTS
import torch

app = Flask(__name__)
CORS(app)
OLLAMA_HOST = os.getenv('OLLAMA_HOST', 'http://ollama:11434')

# Initialize TTS
tts = TTS(model_name="tts_models/en/ljspeech/tacotron2-DDC", progress_bar=False)

@app.route('/generate', methods=['POST'])
def generate_avatar():
    try:
        data = request.json
        prompt = data.get('prompt', 'Hello')
        
        # Create temp files
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as audio_file:
            audio_path = audio_file.name
        
        with tempfile.NamedTemporaryFile(suffix='.mp4', delete=False) as video_file:
            video_path = video_file.name
        
        # Generate speech
        tts.tts_to_file(text=prompt, file_path=audio_path)
        
        # Create simple avatar video (placeholder face with audio)
        create_simple_avatar(audio_path, video_path, prompt)
        
        return send_file(video_path, mimetype='video/mp4')
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

def create_simple_avatar(audio_path, video_path, text):
    # Create a simple animated face
    fps = 30
    duration = 3  # 3 seconds
    frames = fps * duration
    
    fourcc = cv2.VideoWriter_fourcc(*'mp4v')
    out = cv2.VideoWriter(video_path, fourcc, fps, (640, 480))
    
    for i in range(frames):
        # Create frame with animated mouth
        frame = np.zeros((480, 640, 3), dtype=np.uint8)
        frame.fill(50)  # Dark background
        
        # Draw simple face
        cv2.circle(frame, (320, 240), 100, (200, 180, 160), -1)  # Face
        cv2.circle(frame, (290, 210), 10, (0, 0, 0), -1)  # Left eye
        cv2.circle(frame, (350, 210), 10, (0, 0, 0), -1)  # Right eye
        
        # Animate mouth based on frame
        mouth_open = int(10 * abs(np.sin(i * 0.5)))
        cv2.ellipse(frame, (320, 280), (20, mouth_open), 0, 0, 180, (0, 0, 0), -1)
        
        # Add text
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
    return jsonify({'status': 'ok'})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=7860)