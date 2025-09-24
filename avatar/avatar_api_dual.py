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
import cv2
import traceback
from datetime import datetime

app = Flask(__name__)
CORS(app)

# External Service Configuration
TTS_API_URL = os.getenv('TTS_API_URL', 'http://tts:5000')
OLLAMA_HOST = os.getenv('OLLAMA_HOST', 'http://ollama:11434')

# Device Detection
device = "cuda" if torch.cuda.is_available() else "cpu"


def validate_tts_service():
    """Check if TTS service is available"""
    try:
        response = requests.get(f"{TTS_API_URL}/health", timeout=5)
        return response.status_code == 200
    except requests.exceptions.RequestException:
        print(f"TTS Service at {TTS_API_URL} is not available")
        return False


def generate_tts(text):
    """Generate speech from text using external TTS service"""
    try:
        print(f"Calling TTS service at {TTS_API_URL}/synthesize")
        response = requests.post(
            f"{TTS_API_URL}/synthesize",
            json={'text': text},
            timeout=10
        )

        if response.status_code == 200:
            # Save audio to temporary file
            with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as audio_file:
                audio_file.write(response.content)
                audio_path = audio_file.name

            print(f"TTS audio generated: {audio_path}")
            return audio_path
        else:
            print(f"TTS service error: {response.status_code} - {response.text}")
            return None

    except requests.exceptions.RequestException as e:
        print(f"TTS Request failed: {e}")
        return None
    except Exception as e:
        print(f"Unexpected TTS generation error: {e}")
        return None


# [All other existing methods from previous script remain the same]
# Including:
# - generate_talking_face
# - create_default_face
# - create_animated_face
# - convert_video_with_codec
# etc.

@app.route('/generate', methods=['POST'])
def generate_avatar():
    mode = request.args.get('mode', 'simple')
    codec = request.args.get('codec', 'h264_fast')
    quality = request.args.get('quality', 'high')

    print(f"=== AVATAR GENERATION START ===")
    print(f"Mode: {mode}")
    print(f"Codec: {codec}")
    print(f"Quality: {quality}")

    try:
        data = request.json
        if not data:
            return jsonify({'error': 'No JSON data provided'}), 400

        prompt = data.get('prompt', 'Hello')
        source_image = data.get('image', '/app/default_face.jpg')

        # Create temp files
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as audio_file:
            audio_path = audio_file.name

        video_path = '/app/static/avatar_video.avi'
        os.makedirs('/app/static', exist_ok=True)

        # Use external TTS service
        audio_path = generate_tts(prompt)

        if not audio_path:
            return jsonify({'error': 'TTS generation failed'}), 500

        # Rest of the generation logic remains the same
        try:
            if mode == 'sadtalker':
                success = generate_sadtalker_video(audio_path, video_path, prompt, codec, quality)
                if not success:
                    generate_talking_face(source_image, audio_path, video_path, codec, quality)
            else:
                generate_talking_face(source_image, audio_path, video_path, codec, quality)

            # Video conversion and return logic
            final_path = convert_video_with_codec(video_path, audio_path, codec, quality)

            if final_path and os.path.exists(final_path):
                return send_file(final_path, mimetype='video/mp4', as_attachment=False)
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
        print(f"Avatar generation error: {str(e)}")
        print(traceback.format_exc())
        return jsonify({'error': str(e)}), 500

def generate_talking_face(image_path, audio_path, output_path):
    """Generate realistic talking face using MediaPipe"""
    try:
        print("Starting face detection...")
        # Use MediaPipe for face detection
        import mediapipe as mp

        mp_face_detection = mp.solutions.face_detection
        mp_drawing = mp.solutions.drawing_utils

        # Load source image or create default
        if os.path.exists(image_path):
            img = cv2.imread(image_path)
            print(f"Loaded image: {image_path}")
        else:
            print("Creating default face image...")
            img = create_default_face()

        with mp_face_detection.FaceDetection(model_selection=0, min_detection_confidence=0.5) as face_detection:
            results = face_detection.process(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))

            if results.detections:
                print(f"Found {len(results.detections)} faces")
                # Create animated video with detected face
                create_animated_face(img, results.detections[0], audio_path, output_path)
            else:
                print("No face detected, using basic avatar")
                # No face detected, use basic avatar
                create_basic_avatar(audio_path, output_path, "No face detected")

    except Exception as e:
        print(f"Face generation error: {str(e)}")
        # Fallback to basic avatar
        create_basic_avatar(audio_path, output_path, f"Face animation failed: {str(e)}")




@app.route('/debug/status')
def debug_status():
    """Comprehensive debug status"""
    try:
        tts_available = validate_tts_service()

        status = {
            'timestamp': str(datetime.now()),
            'device': device,
            'tts_service': {
                'url': TTS_API_URL,
                'available': tts_available
            },
            'sadtalker_installed': os.path.exists('/app/SadTalker'),
            'modes': ['simple', 'sadtalker'],
            'codecs': ['h264_high', 'h264_medium', 'h264_fast', 'h265_high', 'webm_high', 'webm_fast'],
            'quality_levels': ['high', 'medium', 'fast'],
            'default_codec': 'h264_fast'
        }
        return jsonify(status)
    except Exception as e:
        return jsonify({'error': str(e)})


@app.route('/health')
def health():
    """Health check endpoint"""
    tts_available = validate_tts_service()

    return jsonify({
        'status': 'ok',
        'device': device,
        'tts_service': {
            'url': TTS_API_URL,
            'available': tts_available
        },
        'modes': ['simple', 'sadtalker'],
        'codecs': ['h264_high', 'h264_medium', 'h264_fast', 'h265_high', 'webm_high', 'webm_fast'],
        'default_codec': 'h264_fast',
        'endpoints': [
            '/generate?mode=simple&codec=h264_high&quality=high',
            '/generate?mode=sadtalker&codec=h264_medium&quality=medium',
            '/debug/status',
            '/health'
        ]
    })


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=7860)