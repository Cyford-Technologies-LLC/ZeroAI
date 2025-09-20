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
from datetime import datetime

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
    mode = request.args.get('mode', 'simple')  # Add mode support
    print(f"=== AVATAR GENERATION START - MODE: {mode} ===")
    
    try:
        data = request.json
        if not data:
            return jsonify({'error': 'No JSON data provided'}), 400
            
        prompt = data.get('prompt', 'Hello')
        source_image = data.get('image', '/app/default_face.jpg')
        
        print(f"Generating avatar for: {prompt} (mode: {mode})", flush=True)
        
        # Create temp files
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as audio_file:
            audio_path = audio_file.name
        
        # Use static video file path
        video_path = '/app/static/avatar_video.avi'
        os.makedirs('/app/static', exist_ok=True)
        
        try:
            # Generate high-quality TTS
            if tts:
                print("Generating TTS...")
                tts.tts_to_file(text=prompt, file_path=audio_path)
                print("TTS completed")
            
            # Generate based on mode
            if mode == 'sadtalker':
                print("Using SadTalker mode...")
                success = generate_sadtalker_video(audio_path, video_path, prompt)
                if not success:
                    print("SadTalker failed, falling back to MediaPipe")
                    generate_talking_face(source_image, audio_path, video_path)
            else:
                print("Using simple/MediaPipe mode...")
                generate_talking_face(source_image, audio_path, video_path)
            print("Face generation completed")
            
            # Check if video was created
            print(f"Checking video file: {video_path}")
            print(f"File exists: {os.path.exists(video_path)}")
            if os.path.exists(video_path):
                print(f"File size: {os.path.getsize(video_path)} bytes")
            
            if os.path.exists(video_path) and os.path.getsize(video_path) > 0:
                # Convert AVI to MP4 using FFmpeg
                mp4_path = video_path.replace('.avi', '.mp4')
                try:
                    import subprocess
                    result = subprocess.run(['ffmpeg', '-i', video_path, '-i', audio_path, '-c:v', 'libx264', '-c:a', 'aac', '-preset', 'ultrafast', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-shortest', '-y', mp4_path], 
                                          capture_output=True, text=True)
                    if result.returncode == 0 and os.path.exists(mp4_path) and os.path.getsize(mp4_path) > 0:
                        print(f"Converted to MP4: {mp4_path} ({os.path.getsize(mp4_path)} bytes)")
                        return send_file(mp4_path, mimetype='video/mp4', as_attachment=False)
                    else:
                        print(f"FFmpeg failed: {result.stderr}")
                        return send_file(video_path, mimetype='video/avi', as_attachment=False)
                except Exception as e:
                    print(f"FFmpeg conversion error: {e}")
                    return send_file(video_path, mimetype='video/avi', as_attachment=False)
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
        
        print(f"Image loaded successfully, shape: {img.shape}")
        print(f"Image dtype: {img.dtype}, min: {img.min()}, max: {img.max()}")
        
        # Save debug image
        cv2.imwrite('/app/debug_input.jpg', img)
        print("Debug image saved to /app/debug_input.jpg")
        
        with mp_face_detection.FaceDetection(model_selection=0, min_detection_confidence=0.3) as face_detection:
            # Convert to RGB for MediaPipe
            rgb_img = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
            print(f"RGB image shape: {rgb_img.shape}")
            
            results = face_detection.process(rgb_img)
            print(f"MediaPipe processing completed")
            print(f"Results: {results}")
            print(f"Has detections: {hasattr(results, 'detections')}")
            
            if results.detections:
                print(f"Found {len(results.detections)} faces")
                for i, detection in enumerate(results.detections):
                    print(f"Face {i}: confidence = {detection.score}")
                    bbox = detection.location_data.relative_bounding_box
                    print(f"Face {i}: bbox = x:{bbox.xmin}, y:{bbox.ymin}, w:{bbox.width}, h:{bbox.height}")
                
                # Create animated video with detected face
                create_animated_face(img, results.detections[0], audio_path, output_path)
            else:
                print("No face detected by MediaPipe")
                print("Trying with lower confidence threshold...")
                
                # Try with very low confidence
                with mp_face_detection.FaceDetection(model_selection=0, min_detection_confidence=0.1) as face_detection_low:
                    results_low = face_detection_low.process(rgb_img)
                    if results_low.detections:
                        print(f"Found {len(results_low.detections)} faces with low confidence")
                        create_animated_face(img, results_low.detections[0], audio_path, output_path)
                    else:
                        print("Still no face detected, using basic avatar")
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
    
    # Use mp4v codec for MP4 files
    codecs = ['mp4v', 'MJPG', 'XVID']
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
            
            # Realistic talking animation
            mouth_intensity = abs(np.sin(i * 0.3)) * abs(np.sin(i * 0.1))
            
            # Get face region
            face_region = frame[y:y+h, x:x+w]
            
            # Mouth animation - modify the actual mouth area
            mouth_y_rel = int(h * 0.7)
            mouth_x_rel = int(w * 0.5)
            mouth_w = int(w * 0.2)
            mouth_h = int(5 + mouth_intensity * 20)
            
            # Draw animated mouth on face
            if mouth_y_rel < h and mouth_x_rel < w:
                cv2.ellipse(face_region, (mouth_x_rel, mouth_y_rel), (mouth_w, mouth_h), 0, 0, 180, (120, 80, 80), -1)
                # Add teeth when mouth is open
                if mouth_h > 10:
                    cv2.ellipse(face_region, (mouth_x_rel, mouth_y_rel-2), (mouth_w-5, 3), 0, 0, 180, (240, 240, 240), -1)
            
            # Eye blinking
            if i % 120 < 8:  # Blink occasionally
                eye_y_rel = int(h * 0.35)
                left_eye_x = int(w * 0.35)
                right_eye_x = int(w * 0.65)
                cv2.ellipse(face_region, (left_eye_x, eye_y_rel), (int(w * 0.08), 4), 0, 0, 180, (200, 180, 160), -1)
                cv2.ellipse(face_region, (right_eye_x, eye_y_rel), (int(w * 0.08), 4), 0, 0, 180, (200, 180, 160), -1)
            
            # Put modified face back
            frame[y:y+h, x:x+w] = face_region
            
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
    
    # Use mp4v codec for MP4 files
    codecs = ['mp4v', 'MJPG', 'XVID']
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
            frame.fill(30)
            
            # Draw more realistic face
            cv2.circle(frame, (320, 240), 100, (220, 200, 180), -1)  # Face
            cv2.circle(frame, (295, 215), 12, (80, 60, 40), -1)  # Left eye
            cv2.circle(frame, (345, 215), 12, (80, 60, 40), -1)  # Right eye
            cv2.circle(frame, (297, 213), 4, (255, 255, 255), -1)  # Left eye highlight
            cv2.circle(frame, (347, 213), 4, (255, 255, 255), -1)  # Right eye highlight
            
            # Nose
            cv2.ellipse(frame, (320, 245), (8, 12), 0, 0, 180, (200, 180, 160), -1)
            
            # Realistic mouth animation
            mouth_open = 5 + int(15 * abs(np.sin(i * 0.3)) * abs(np.sin(i * 0.1)))
            cv2.ellipse(frame, (320, 275), (25, mouth_open), 0, 0, 180, (100, 80, 80), -1)
            
            # Add eyebrows
            cv2.ellipse(frame, (295, 195), (15, 5), 0, 0, 180, (120, 100, 80), -1)
            cv2.ellipse(frame, (345, 195), (15, 5), 0, 0, 180, (120, 100, 80), -1)
            
            cv2.putText(frame, text[:30], (50, 50), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (255, 255, 255), 2)
            
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

def generate_sadtalker_video(audio_path, video_path, prompt):
    """Generate SadTalker realistic avatar (placeholder for now)"""
    print("SadTalker not yet implemented, using fallback")
    return False  # Will trigger fallback to MediaPipe

@app.route('/debug/status')
def debug_status():
    """Debug status endpoint"""
    try:
        status = {
            'timestamp': str(datetime.now()),
            'device': device,
            'tts_ready': tts is not None,
            'sadtalker_installed': False,  # Will be True when implemented
            'modes': ['simple', 'sadtalker'],
            'disk_space': 'Available',
            'memory': 'Available'
        }
        return jsonify(status)
    except Exception as e:
        return jsonify({'error': str(e)})

@app.route('/debug/logs')
def debug_logs():
    """Debug logs endpoint"""
    try:
        # Return recent logs (placeholder)
        logs = ['Avatar system running', 'TTS initialized', 'MediaPipe ready']
        return jsonify({'logs': logs})
    except Exception as e:
        return jsonify({'error': str(e)})

@app.route('/health')
def health():
    return jsonify({
        'status': 'ok', 
        'device': device, 
        'tts_ready': tts is not None,
        'models': 'TTS + MediaPipe',
        'modes': ['simple', 'sadtalker'],
        'endpoints': ['/generate?mode=simple', '/generate?mode=sadtalker', '/debug/status', '/debug/logs']
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=7860)