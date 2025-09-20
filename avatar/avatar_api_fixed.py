from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import os
import tempfile
import torch
import numpy as np
import cv2
import traceback
import logging

app = Flask(__name__)
CORS(app)

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Initialize TTS with error handling
device = "cuda" if torch.cuda.is_available() else "cpu"
tts = None

try:
    from TTS.api import TTS
    tts = TTS("tts_models/en/ljspeech/tacotron2-DDC_ph", gpu=(device=="cuda"))
    logger.info("TTS initialized successfully")
except Exception as e:
    logger.error(f"TTS initialization failed: {e}")

@app.route('/generate', methods=['POST'])
def generate_avatar():
    try:
        if not request.json:
            return jsonify({'error': 'No JSON data'}), 400
            
        prompt = request.json.get('prompt', 'Hello')
        logger.info(f"Generating avatar for: {prompt}")
        
        # Create temp files
        audio_path = tempfile.mktemp(suffix='.wav')
        video_path = tempfile.mktemp(suffix='.mp4')
        
        try:
            # Generate TTS
            if tts:
                tts.tts_to_file(text=prompt, file_path=audio_path)
            
            # Create video
            create_video(prompt, video_path)
            
            if os.path.exists(video_path) and os.path.getsize(video_path) > 0:
                return send_file(video_path, mimetype='video/mp4')
            else:
                return jsonify({'error': 'Video creation failed'}), 500
                
        finally:
            # Cleanup
            for path in [audio_path, video_path]:
                try:
                    if os.path.exists(path):
                        os.unlink(path)
                except:
                    pass
        
    except Exception as e:
        logger.error(f"Error: {e}")
        return jsonify({'error': str(e)}), 500

def create_video(text, output_path):
    """Create simple animated video"""
    fps = 30
    duration = 3
    frames = fps * duration
    
    fourcc = cv2.VideoWriter_fourcc(*'mp4v')
    out = cv2.VideoWriter(output_path, fourcc, fps, (640, 480))
    
    if not out.isOpened():
        raise Exception("Video writer failed")
    
    try:
        for i in range(frames):
            frame = np.zeros((480, 640, 3), dtype=np.uint8)
            frame.fill(30)
            
            # Draw face
            cv2.circle(frame, (320, 240), 80, (200, 180, 160), -1)
            cv2.circle(frame, (300, 220), 8, (0, 0, 0), -1)
            cv2.circle(frame, (340, 220), 8, (0, 0, 0), -1)
            
            # Animate mouth
            mouth_h = int(5 + 10 * abs(np.sin(i * 0.3)))
            cv2.ellipse(frame, (320, 270), (15, mouth_h), 0, 0, 180, (0, 0, 0), -1)
            
            # Add text
            cv2.putText(frame, text[:40], (50, 50), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
            
            out.write(frame)
    finally:
        out.release()

@app.route('/health')
def health():
    return jsonify({
        'status': 'ok',
        'device': device,
        'tts_ready': tts is not None
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=7860)