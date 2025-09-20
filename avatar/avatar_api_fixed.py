from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import os
import tempfile
import torch
import numpy as np
from TTS.api import TTS
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
    logger.info("Initializing TTS...")
    tts = TTS("tts_models/en/ljspeech/tacotron2-DDC_ph", gpu=(device=="cuda"))
    logger.info("TTS initialized successfully")
except Exception as e:
    logger.error(f"TTS initialization failed: {e}")
    tts = None

@app.route('/generate', methods=['POST'])
def generate_avatar():
    try:
        if tts is None:
            return jsonify({'error': 'TTS not initialized'}), 500
            
        data = request.json
        if not data:
            return jsonify({'error': 'No JSON data provided'}), 400
            
        prompt = data.get('prompt', 'Hello')
        logger.info(f"Generating avatar for: {prompt}")
        
        # Create temp files
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as audio_file:
            audio_path = audio_file.name
        
        with tempfile.NamedTemporaryFile(suffix='.mp4', delete=False) as video_file:
            video_path = video_file.name
        
        try:
            # Generate TTS
            logger.info("Generating TTS...")
            tts.tts_to_file(text=prompt, file_path=audio_path)
            logger.info("TTS completed")
            
            # Create basic video
            logger.info("Creating video...")
            create_basic_video(prompt, video_path)
            logger.info("Video completed")
            
            # Check if video was created
            if os.path.exists(video_path) and os.path.getsize(video_path) > 0:
                logger.info(f"Video created: {os.path.getsize(video_path)} bytes")
                return send_file(video_path, mimetype='video/mp4')
            else:
                return jsonify({'error': 'Video creation failed'}), 500
                
        finally:
            # Cleanup temp files
            try:
                if os.path.exists(audio_path):
                    os.unlink(audio_path)
            except:
                pass
        
    except Exception as e:
        logger.error(f"Avatar generation error: {str(e)}")
        logger.error(traceback.format_exc())
        return jsonify({'error': str(e)}), 500

def create_basic_video(text, output_path):
    """Create a simple animated video"""
    fps = 30
    duration = 3
    frames = fps * duration
    
    # Use a codec that works reliably
    fourcc = cv2.VideoWriter_fourcc(*'mp4v')
    out = cv2.VideoWriter(output_path, fourcc, fps, (640, 480))
    
    if not out.isOpened():
        raise Exception("Could not open video writer")
    
    try:
        for i in range(frames):
            frame = np.zeros((480, 640, 3), dtype=np.uint8)
            frame.fill(30)  # Dark background
            
            # Draw simple face
            cv2.circle(frame, (320, 240), 80, (200, 180, 160), -1)  # Face
            cv2.circle(frame, (300, 220), 8, (0, 0, 0), -1)  # Left eye
            cv2.circle(frame, (340, 220), 8, (0, 0, 0), -1)  # Right eye
            
            # Animate mouth
            mouth_height = int(5 + 10 * abs(np.sin(i * 0.3)))
            cv2.ellipse(frame, (320, 270), (15, mouth_height), 0, 0, 180, (0, 0, 0), -1)
            
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
    app.run(host='0.0.0.0', port=7860, debug=False)