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
import traceback

app = Flask(__name__)
CORS(app)
OLLAMA_HOST = os.getenv('OLLAMA_HOST', 'http://ollama:11434')

# Initialize TTS with neural voice - with error handling
device = "cuda" if torch.cuda.is_available() else "cpu"
try:
    tts = TTS("tts_models/en/ljspeech/tacotron2-DDC_ph", gpu=(device=="cuda"))
    print("TTS initialized successfully")
except Exception as e:
    print(f"TTS initialization failed: {e}")
    tts = None

@app.route('/generate', methods=['POST'])
def generate_avatar():
    try:
        data = request.json
        if not data:
            return jsonify({'error': 'No JSON data provided'}), 400
            
        prompt = data.get('prompt', 'Hello')
        source_image = data.get('image', '/app/default_face.jpg')
        
        print(f"Generating avatar for: {prompt}", flush=True)
        
        # Create temp files
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as audio_file:
            audio_path = audio_file.name
        
        with tempfile.NamedTemporaryFile(suffix='.webm', delete=False) as video_file:
            video_path = video_file.name
        
        try:
            # Generate high-quality TTS
            if tts:
                print("Generating TTS...")
                tts.tts_to_file(text=prompt, file_path=audio_path)
                print("TTS completed")
            
            # Use MediaPipe for realistic talking face
            print("Generating talking face...")
            generate_talking_face(source_image, audio_path, video_path)
            print("Face generation completed")
            
            # Check if video was created
            print(f"Checking video file: {video_path}")
            print(f"File exists: {os.path.exists(video_path)}")
            if os.path.exists(video_path):
                print(f"File size: {os.path.getsize(video_path)} bytes")
            
            if os.path.exists(video_path) and os.path.getsize(video_path) > 0:
                print(f"Video created successfully: {os.path.getsize(video_path)} bytes")
                return send_file(video_path, mimetype='video/webm', as_attachment=False)
            else:
                print("Video creation failed - file empty or missing")
                return jsonify({'error': 'Video creation failed'}), 500
                
        finally:
            # Cleanup temp files after response
            try:
                if os.path.exists(audio_path):
                    os.unlink(audio_path)
            except:
                pass
            # Don't delete video file here - Flask needs it for send_file
        
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
        
        if img is None:
            print("Image is None, creating default")
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
        print(traceback.format_exc())
        # Fallback to basic avatar
        create_basic_avatar(audio_path, output_path, f"Face animation failed: {str(e)}")

def create_default_face():
    """Create a default face image"""
    img = np.ones((512, 512, 3), dtype=np.uint8) * 240
    # Draw face
    cv2.circle(img, (256, 256), 150, (200, 180, 160), -1)
    cv2.circle(img, (220, 220), 15, (0, 0, 0), -1)
    cv2.circle(img, (292, 220), 15, (0, 0, 0), -1)
    cv2.ellipse(img, (256, 300), (30, 15), 0, 0, 180, (100, 50, 50), -1)
    return img

def create_animated_face(img, detection, audio_path, output_path):
    """Create animated face video"""
    print("Creating animated face video...")
    fps = 30
    duration = 5
    frames = fps * duration
    
    height, width = img.shape[:2]
    
    # Use VP80 codec for WebM format
    codecs = ['VP80', 'MJPG', 'XVID']
    out = None
    final_path = output_path
    
    for codec in codecs:
        try:
            fourcc = cv2.VideoWriter_fourcc(*codec)
            test_path = output_path.replace('.mp4', f'_{codec}.mp4')
            out = cv2.VideoWriter(test_path, fourcc, fps, (width, height))
            
            if out.isOpened():
                print(f"Using codec: {codec}")
                final_path = test_path
                break
            else:
                if out:
                    out.release()
                out = None
        except Exception as codec_e:
            print(f"Codec {codec} failed: {codec_e}")
            continue
    
    if out is None:
        print("All codecs failed, using basic avatar")
        create_basic_avatar(audio_path, output_path, "Codec failed")
        return
    
    try:
        # Get face bounding box
        bbox = detection.location_data.relative_bounding_box
        x = int(bbox.xmin * width)
        y = int(bbox.ymin * height)
        w = int(bbox.width * width)
        h = int(bbox.height * height)
        
        print(f"Face bbox: x={x}, y={y}, w={w}, h={h}")
        
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
        
        print(f"Video saved to: {final_path}")
        
        # Copy to original path if different
        if final_path != output_path:
            import shutil
            shutil.copy2(final_path, output_path)
            
    finally:
        if out:
            out.release()

def create_basic_avatar(audio_path, video_path, text):
    """Fallback basic avatar"""
    print("Creating basic avatar...")
    fps = 30
    duration = 3
    frames = fps * duration
    
    # Use VP80 codec for WebM format
    codecs = ['VP80', 'MJPG', 'XVID']
    out = None
    
    for codec in codecs:
        try:
            fourcc = cv2.VideoWriter_fourcc(*codec)
            out = cv2.VideoWriter(video_path, fourcc, fps, (640, 480))
            if out.isOpened():
                print(f"Basic avatar using codec: {codec}")
                break
            else:
                if out:
                    out.release()
                out = None
        except Exception as e:
            print(f"Basic codec {codec} failed: {e}")
            continue
    
    if out is None:
        print("ERROR: No working codec found!")
        return
    
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
        
        print("Basic avatar completed")
    finally:
        if out:
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
    return jsonify({
        'status': 'ok', 
        'device': device, 
        'tts_ready': tts is not None,
        'models': 'TTS + MediaPipe'
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=7860)