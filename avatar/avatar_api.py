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
import logging
import sys

# Set up detailed logging
logging.basicConfig(level=logging.DEBUG)
logger = logging.getLogger(__name__)

app = Flask(__name__)
CORS(app)
OLLAMA_HOST = os.getenv('OLLAMA_HOST', 'http://ollama:11434')

# Initialize TTS with detailed error handling
device = "cuda" if torch.cuda.is_available() else "cpu"
tts = None
tts_error = None

try:
    logger.info("Initializing TTS...")
    from TTS.api import TTS
    tts = TTS("tts_models/en/ljspeech/tacotron2-DDC_ph", gpu=(device=="cuda"))
    logger.info("TTS initialized successfully")
except Exception as e:
    tts_error = str(e)
    logger.error(f"TTS initialization failed: {e}")
    logger.error(traceback.format_exc())

@app.route('/generate', methods=['POST'])
def generate_avatar():
    try:
        logger.info("=== Starting avatar generation ===")
        
        if tts is None:
            logger.error(f"TTS not available: {tts_error}")
            return jsonify({'error': f'TTS not initialized: {tts_error}'}), 500
        
        data = request.json
        if not data:
            logger.error("No JSON data provided")
            return jsonify({'error': 'No JSON data provided'}), 400
            
        prompt = data.get('prompt', 'Hello')
        source_image = data.get('image', '/app/default_face.jpg')
        
        logger.info(f"Generating avatar for: {prompt}")
        logger.info(f"Source image: {source_image}")
        
        # Create temp files
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as audio_file:
            audio_path = audio_file.name
        
        with tempfile.NamedTemporaryFile(suffix='.mp4', delete=False) as video_file:
            video_path = video_file.name
        
        logger.info(f"Audio path: {audio_path}")
        logger.info(f"Video path: {video_path}")
        
        try:
            # Generate high-quality TTS
            logger.info("Generating TTS...")
            tts.tts_to_file(text=prompt, file_path=audio_path)
            logger.info("TTS completed successfully")
            
            # Use MediaPipe for realistic talking face
            logger.info("Generating talking face...")
            generate_talking_face(source_image, audio_path, video_path)
            logger.info("Face generation completed")
            
            # Check if video was created
            if os.path.exists(video_path) and os.path.getsize(video_path) > 0:
                logger.info(f"Video created successfully: {os.path.getsize(video_path)} bytes")
                return send_file(video_path, mimetype='video/mp4')
            else:
                logger.error("Video creation failed - file empty or missing")
                return jsonify({'error': 'Video creation failed'}), 500
                
        except Exception as inner_e:
            logger.error(f"Inner generation error: {str(inner_e)}")
            logger.error(traceback.format_exc())
            raise inner_e
        finally:
            # Cleanup temp files
            try:
                if os.path.exists(audio_path):
                    os.unlink(audio_path)
                    logger.info("Cleaned up audio file")
            except Exception as cleanup_e:
                logger.warning(f"Audio cleanup failed: {cleanup_e}")
        
    except Exception as e:
        logger.error(f"Avatar generation error: {str(e)}")
        logger.error(traceback.format_exc())
        return jsonify({'error': str(e), 'traceback': traceback.format_exc()}), 500

def generate_talking_face(image_path, audio_path, output_path):
    """Generate realistic talking face using MediaPipe"""
    try:
        logger.info("Starting face detection...")
        
        # Import MediaPipe with error handling
        try:
            import mediapipe as mp
            logger.info("MediaPipe imported successfully")
        except Exception as mp_e:
            logger.error(f"MediaPipe import failed: {mp_e}")
            raise mp_e
        
        mp_face_detection = mp.solutions.face_detection
        mp_drawing = mp.solutions.drawing_utils
        
        # Load source image or create default
        if os.path.exists(image_path):
            img = cv2.imread(image_path)
            logger.info(f"Loaded image: {image_path}")
        else:
            logger.info("Creating default face image...")
            img = create_default_face()
        
        if img is None:
            logger.error("Failed to load or create image")
            raise Exception("Image loading failed")
        
        logger.info(f"Image shape: {img.shape}")
        
        with mp_face_detection.FaceDetection(model_selection=0, min_detection_confidence=0.5) as face_detection:
            results = face_detection.process(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))
            
            if results.detections:
                logger.info(f"Found {len(results.detections)} faces")
                # Create animated video with detected face
                create_animated_face(img, results.detections[0], audio_path, output_path)
            else:
                logger.info("No face detected, using basic avatar")
                # No face detected, use basic avatar
                create_basic_avatar(audio_path, output_path, "No face detected")
                
    except Exception as e:
        logger.error(f"Face generation error: {str(e)}")
        logger.error(traceback.format_exc())
        # Fallback to basic avatar
        create_basic_avatar(audio_path, output_path, f"Face animation failed: {str(e)}")

def create_default_face():
    """Create a default face image"""
    logger.info("Creating default face...")
    img = np.ones((512, 512, 3), dtype=np.uint8) * 240
    # Draw face
    cv2.circle(img, (256, 256), 150, (200, 180, 160), -1)
    cv2.circle(img, (220, 220), 15, (0, 0, 0), -1)
    cv2.circle(img, (292, 220), 15, (0, 0, 0), -1)
    cv2.ellipse(img, (256, 300), (30, 15), 0, 0, 180, (100, 50, 50), -1)
    logger.info("Default face created")
    return img

def create_animated_face(img, detection, audio_path, output_path):
    """Create animated face video"""
    logger.info("Creating animated face video...")
    fps = 30
    duration = 5
    frames = fps * duration
    
    height, width = img.shape[:2]
    logger.info(f"Video dimensions: {width}x{height}")
    
    # Try multiple codecs until one works
    codecs = ['mp4v', 'XVID', 'MJPG']
    out = None
    working_codec = None
    
    for codec in codecs:
        try:
            logger.info(f"Trying codec: {codec}")
            fourcc = cv2.VideoWriter_fourcc(*codec)
            test_path = output_path.replace('.mp4', f'_{codec}.mp4')
            out = cv2.VideoWriter(test_path, fourcc, fps, (width, height))
            
            if out.isOpened():
                logger.info(f"Using codec: {codec}")
                working_codec = codec
                output_path = test_path
                break
            else:
                logger.warning(f"Codec {codec} failed to open")
                out.release()
                out = None
        except Exception as codec_e:
            logger.warning(f"Codec {codec} exception: {codec_e}")
            continue
    
    if out is None:
        logger.error("All codecs failed, using basic avatar")
        create_basic_avatar(audio_path, output_path, "Codec failed")
        return
    
    try:
        # Get face bounding box
        bbox = detection.location_data.relative_bounding_box
        x = int(bbox.xmin * width)
        y = int(bbox.ymin * height)
        w = int(bbox.width * width)
        h = int(bbox.height * height)
        
        logger.info(f"Face bbox: x={x}, y={y}, w={w}, h={h}")
        
        for i in range(frames):
            frame = img.copy()
            
            # Animate mouth area based on audio (simple sine wave)
            mouth_scale = 1.0 + 0.3 * abs(np.sin(i * 0.3))
            
            # Apply simple mouth animation
            mouth_y = int(y + h * 0.7)
            mouth_x = int(x + w / 2)
            
            # Draw animated mouth indicator
            mouth_size = int(10 * mouth_scale)
            cv2.circle(frame, (mouth_x, mouth_y), mouth_size, (0, 0, 255), 2)
            
            # Draw face detection box
            cv2.rectangle(frame, (x, y), (x + w, y + h), (0, 255, 0), 2)
            
            out.write(frame)
        
        logger.info(f"Video saved to: {output_path}")
    finally:
        out.release()

def create_basic_avatar(audio_path, video_path, text):
    """Fallback basic avatar"""
    logger.info("Creating basic avatar...")
    fps = 30
    duration = 3
    frames = fps * duration
    
    # Try multiple codecs
    codecs = ['mp4v', 'XVID', 'MJPG']
    out = None
    
    for codec in codecs:
        try:
            logger.info(f"Basic avatar trying codec: {codec}")
            fourcc = cv2.VideoWriter_fourcc(*codec)
            out = cv2.VideoWriter(video_path, fourcc, fps, (640, 480))
            if out.isOpened():
                logger.info(f"Basic avatar using codec: {codec}")
                break
            else:
                out.release()
                out = None
        except Exception as e:
            logger.warning(f"Basic avatar codec {codec} failed: {e}")
            continue
    
    if out is None:
        logger.error("ERROR: No working codec found!")
        raise Exception("No working video codec found")
    
    try:
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
        
        logger.info("Basic avatar completed")
    finally:
        out.release()

@app.route('/analyze', methods=['POST'])
def analyze_image():
    try:
        question = request.form.get('question', 'What do you see?')
        return jsonify({'analysis': f'Image analysis: {question}'})
    except Exception as e:
        logger.error(f"Analyze error: {e}")
        return jsonify({'error': str(e)}), 500

@app.route('/health')
def health():
    return jsonify({
        'status': 'ok', 
        'device': device, 
        'tts_ready': tts is not None,
        'tts_error': tts_error,
        'models': 'TTS + MediaPipe'
    })

@app.route('/debug')
def debug():
    try:
        debug_info = {
            'python_version': sys.version,
            'opencv_version': cv2.__version__,
            'torch_available': torch.cuda.is_available(),
            'device': device,
            'tts_ready': tts is not None,
            'tts_error': tts_error
        }
        
        try:
            import mediapipe as mp
            debug_info['mediapipe_version'] = mp.__version__
        except:
            debug_info['mediapipe_error'] = 'Import failed'
            
        return jsonify(debug_info)
    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    logger.info(f"Starting avatar API on Python {sys.version}")
    app.run(host='0.0.0.0', port=7860, debug=True)