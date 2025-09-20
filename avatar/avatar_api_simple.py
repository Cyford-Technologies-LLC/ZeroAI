from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import os
import tempfile
import subprocess
import cv2
import numpy as np

app = Flask(__name__)
CORS(app)

@app.route('/generate', methods=['POST'])
def generate_avatar():
    try:
        data = request.json
        prompt = data.get('prompt', 'Hello')
        
        # Create temp video file
        with tempfile.NamedTemporaryFile(suffix='.avi', delete=False) as video_file:
            video_path = video_file.name
        
        # Create simple working video
        create_working_video(video_path, prompt)
        
        return send_file(video_path, mimetype='video/avi')
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500

def create_working_video(video_path, text):
    """Create a simple working video"""
    fps = 30
    duration = 3
    frames = fps * duration
    
    # Use a codec that definitely works
    fourcc = cv2.VideoWriter_fourcc(*'MJPG')
    out = cv2.VideoWriter(video_path, fourcc, fps, (640, 480))
    
    for i in range(frames):
        # Create frame
        frame = np.zeros((480, 640, 3), dtype=np.uint8)
        frame.fill(50)  # Dark background
        
        # Draw simple animated face
        cv2.circle(frame, (320, 240), 100, (200, 180, 160), -1)  # Face
        cv2.circle(frame, (290, 210), 10, (0, 0, 0), -1)  # Left eye
        cv2.circle(frame, (350, 210), 10, (0, 0, 0), -1)  # Right eye
        
        # Animate mouth
        mouth_open = int(10 * abs(np.sin(i * 0.5)))
        cv2.ellipse(frame, (320, 280), (20, mouth_open), 0, 0, 180, (0, 0, 0), -1)
        
        # Add text
        cv2.putText(frame, text[:30], (50, 50), cv2.FONT_HERSHEY_SIMPLEX, 1, (255, 255, 255), 2)
        
        out.write(frame)
    
    out.release()

@app.route('/health')
def health():
    return jsonify({'status': 'ok', 'message': 'Simple avatar working'})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=7860)