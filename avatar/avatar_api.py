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

# Global default codec
DEFAULT_CODEC = 'h264_fast'

@app.route('/generate', methods=['POST'])
def generate_avatar():
    mode = request.args.get('mode', 'simple')
    codec = request.args.get('codec', DEFAULT_CODEC)  # Use global default
    quality = request.args.get('quality', 'high')   # Add quality support
    
    print(f"=== AVATAR GENERATION START ===")
    print(f"Mode: {mode}")
    print(f"Codec: {codec}")
    print(f"Quality: {quality}")
    print(f"Request args: {dict(request.args)}")
    
    try:
        data = request.json
        if not data:
            print("ERROR: No JSON data provided")
            return jsonify({'error': 'No JSON data provided'}), 400
            
        prompt = data.get('prompt', 'Hello')
        source_image = data.get('image', '/app/default_face.jpg')
        codec_options = data.get('codec_options', {})
        
        print(f"Prompt: {prompt[:50]}...")
        print(f"Source image: {source_image}")
        print(f"Codec options: {codec_options}")
        
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
                print("=== ATTEMPTING SADTALKER MODE ===")
                success = generate_sadtalker_video(audio_path, video_path, prompt, codec, quality)
                if not success:
                    print("=== SADTALKER FAILED - FALLBACK TO MEDIAPIPE ===")
                    generate_talking_face(source_image, audio_path, video_path, codec, quality)
                else:
                    print("=== SADTALKER SUCCESS ===")
            else:
                print("=== USING SIMPLE/MEDIAPIPE MODE ===")
                generate_talking_face(source_image, audio_path, video_path, codec, quality)
            print("Face generation completed")
            
            # Check if video was created
            print(f"Checking video file: {video_path}")
            print(f"File exists: {os.path.exists(video_path)}")
            if os.path.exists(video_path):
                print(f"File size: {os.path.getsize(video_path)} bytes")
            
            if os.path.exists(video_path) and os.path.getsize(video_path) > 0:
                # Convert to final format using FFmpeg with codec support
                final_path = convert_video_with_codec(video_path, audio_path, codec, quality)
                if final_path and os.path.exists(final_path) and os.path.getsize(final_path) > 0:
                    print(f"Final video: {final_path} ({os.path.getsize(final_path)} bytes)")
                    return send_file(final_path, mimetype=get_mimetype_for_codec(codec), as_attachment=False)
                else:
                    print("Codec conversion failed, returning original AVI")
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

def generate_talking_face(image_path, audio_path, output_path, codec='h264_high', quality='high'):
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
                create_animated_face(img, results.detections[0], audio_path, output_path, codec, quality)
            else:
                print("No face detected by MediaPipe")
                print("Trying with lower confidence threshold...")
                
                # Try with very low confidence
                with mp_face_detection.FaceDetection(model_selection=0, min_detection_confidence=0.1) as face_detection_low:
                    results_low = face_detection_low.process(rgb_img)
                    if results_low.detections:
                        print(f"Found {len(results_low.detections)} faces with low confidence")
                        create_animated_face(img, results_low.detections[0], audio_path, output_path, codec, quality)
                    else:
                        print("Still no face detected, using basic avatar")
                        create_basic_avatar(audio_path, output_path, "No face detected", codec, quality)
                
    except Exception as e:
        print(f"Face generation error: {str(e)}")
        print(traceback.format_exc())
        # Fallback to basic avatar
        create_basic_avatar(audio_path, output_path, f"Face animation failed: {str(e)}", codec, quality)

def create_default_face():
    """Create a default face image"""
    img = np.ones((512, 512, 3), dtype=np.uint8) * 240
    # Draw face
    cv2.circle(img, (256, 256), 150, (200, 180, 160), -1)
    cv2.circle(img, (220, 220), 15, (0, 0, 0), -1)
    cv2.circle(img, (292, 220), 15, (0, 0, 0), -1)
    cv2.ellipse(img, (256, 300), (30, 15), 0, 0, 180, (100, 50, 50), -1)
    return img

def create_animated_face(img, detection, audio_path, output_path, codec='h264_high', quality='high'):
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

def create_basic_avatar(audio_path, video_path, text, codec='h264_high', quality='high'):
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

def convert_video_with_codec(video_path, audio_path, codec, quality):
    """Convert video using specified codec with comprehensive logging"""
    print(f"=== VIDEO CODEC CONVERSION START ===")
    print(f"Input video: {video_path}")
    print(f"Input audio: {audio_path}")
    print(f"Target codec: {codec}")
    print(f"Quality: {quality}")
    
    # Define codec configurations
    codec_configs = {
        'h264_high': {
            'video_codec': 'libx264',
            'audio_codec': 'aac',
            'preset': 'ultrafast',
            'crf': '23',
            'profile': 'baseline',
            'level': '3.0',
            'extension': '.mp4'
        },
        'h264_medium': {
            'video_codec': 'libx264',
            'audio_codec': 'aac',
            'preset': 'medium',
            'crf': '23',
            'profile': 'main',
            'level': '4.0',
            'extension': '.mp4'
        },
        'h264_fast': {
            'video_codec': 'libx264',
            'audio_codec': 'aac',
            'preset': 'ultrafast',
            'crf': '28',
            'profile': 'baseline',
            'level': '3.1',
            'extension': '.mp4'
        },
        'h265_high': {
            'video_codec': 'libx265',
            'audio_codec': 'aac',
            'preset': 'slow',
            'crf': '20',
            'extension': '.mp4'
        },
        'webm_high': {
            'video_codec': 'libvpx-vp9',
            'audio_codec': 'libopus',
            'crf': '20',
            'b:v': '2M',
            'extension': '.webm'
        },
        'webm_fast': {
            'video_codec': 'libvpx',
            'audio_codec': 'libvorbis',
            'crf': '30',
            'b:v': '500k',
            'b:a': '96k',
            'cpu-used': '5',
            'deadline': 'realtime',
            'extension': '.webm'
        }
    }
    
    # Get codec configuration
    config = codec_configs.get(codec, codec_configs['h264_high'])
    print(f"Using codec config: {config}")
    
    # Create output path
    output_path = video_path.replace('.avi', config['extension'])
    print(f"Output path: {output_path}")
    
    try:
        # Build FFmpeg command
        cmd = ['ffmpeg', '-i', video_path, '-i', audio_path]
        
        # Video codec settings
        cmd.extend(['-c:v', config['video_codec']])
        if 'preset' in config:
            cmd.extend(['-preset', config['preset']])
        if 'crf' in config:
            cmd.extend(['-crf', config['crf']])
        if 'profile' in config:
            cmd.extend(['-profile:v', config['profile']])
        if 'level' in config:
            cmd.extend(['-level', config['level']])
        if 'b:v' in config:
            cmd.extend(['-b:v', config['b:v']])
        
        # Audio codec settings
        cmd.extend(['-c:a', config['audio_codec']])
        if 'b:a' in config:
            cmd.extend(['-b:a', config['b:a']])
        
        # WebM-specific settings
        if config['video_codec'] == 'libvpx':
            if 'cpu-used' in config:
                cmd.extend(['-cpu-used', config['cpu-used']])
            if 'deadline' in config:
                cmd.extend(['-deadline', config['deadline']])
            cmd.extend(['-auto-alt-ref', '0'])  # Disable alt-ref for compatibility
            cmd.extend(['-lag-in-frames', '0'])  # No frame delay
        
        # Universal compatibility settings
        cmd.extend(['-pix_fmt', 'yuv420p'])
        if config['extension'] == '.mp4':
            cmd.extend(['-movflags', '+faststart'])
        cmd.extend(['-shortest'])
        cmd.extend(['-y', output_path])
        
        print(f"FFmpeg command: {' '.join(cmd)}")
        
        # Execute FFmpeg
        result = subprocess.run(cmd, capture_output=True, text=True)
        
        print(f"FFmpeg return code: {result.returncode}")
        print(f"FFmpeg stdout: {result.stdout}")
        if result.stderr:
            print(f"FFmpeg stderr: {result.stderr}")
        
        # Check result
        if result.returncode == 0 and os.path.exists(output_path):
            output_size = os.path.getsize(output_path)
            print(f"Conversion successful: {output_size} bytes")
            
            # Verify video integrity
            verify_cmd = ['ffprobe', '-v', 'quiet', '-print_format', 'json', '-show_format', '-show_streams', output_path]
            verify_result = subprocess.run(verify_cmd, capture_output=True, text=True)
            
            if verify_result.returncode == 0:
                print(f"Video verification passed")
                print(f"Video info: {verify_result.stdout[:200]}...")
                return output_path
            else:
                print(f"Video verification failed: {verify_result.stderr}")
                return None
        else:
            print(f"FFmpeg conversion failed")
            return None
            
    except Exception as e:
        print(f"Codec conversion error: {e}")
        print(f"Exception traceback: {traceback.format_exc()}")
        return None
    
    finally:
        print(f"=== VIDEO CODEC CONVERSION END ===")

def get_mimetype_for_codec(codec):
    """Get MIME type for codec"""
    mime_types = {
        'h264_high': 'video/mp4',
        'h264_medium': 'video/mp4',
        'h264_fast': 'video/mp4',
        'h265_high': 'video/mp4',
        'webm_high': 'video/webm',
        'webm_fast': 'video/webm'
    }
    return mime_types.get(codec, 'video/mp4')

def generate_sadtalker_video(audio_path, video_path, prompt, codec='h264_high', quality='high'):
    """Generate SadTalker realistic avatar using subprocess"""
    print(f"=== SADTALKER DETAILED DEBUG START ===")
    print(f"Audio path: {audio_path}")
    print(f"Video path: {video_path}")
    print(f"Prompt: {prompt[:50]}...")
    
    try:
        # Check if SadTalker is available
        sadtalker_path = '/app/SadTalker'
        print(f"Checking SadTalker path: {sadtalker_path}")
        print(f"SadTalker exists: {os.path.exists(sadtalker_path)}")
        
        if os.path.exists(sadtalker_path):
            print(f"SadTalker directory contents: {os.listdir(sadtalker_path)}")
            inference_path = f'{sadtalker_path}/inference.py'
            print(f"Inference script exists: {os.path.exists(inference_path)}")
        
        if not os.path.exists(sadtalker_path):
            print("FAILURE REASON: SadTalker directory not found")
            return False
            
        # Create reference image
        ref_image_path = os.path.join(os.path.dirname(video_path), 'ref_face.jpg')
        print(f"Creating reference image: {ref_image_path}")
        default_face = create_default_face()
        cv2.imwrite(ref_image_path, default_face)
        print(f"Reference image created: {os.path.exists(ref_image_path)}")
        
        # Run SadTalker via subprocess
        cmd = [
            'python', f'{sadtalker_path}/inference.py',
            '--driven_audio', audio_path,
            '--source_image', ref_image_path,
            '--result_dir', os.path.dirname(video_path),
            '--enhancer', 'gfpgan',
            '--preprocess', 'crop',
            '--size', '512'
        ]
        
        print(f"SadTalker command: {' '.join(cmd)}")
        print("Executing SadTalker...")
        
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
        
        print(f"SadTalker return code: {result.returncode}")
        print(f"SadTalker stdout: {result.stdout}")
        print(f"SadTalker stderr: {result.stderr}")
        
        if result.returncode == 0:
            # Find generated video and move to expected location
            result_dir = os.path.dirname(video_path)
            print(f"Checking result directory: {result_dir}")
            print(f"Directory contents: {os.listdir(result_dir)}")
            
            for file in os.listdir(result_dir):
                if file.endswith('.mp4') and 'result' in file:
                    generated_path = os.path.join(result_dir, file)
                    print(f"Found generated video: {generated_path}")
                    os.rename(generated_path, video_path)
                    print(f"SadTalker SUCCESS: {os.path.getsize(video_path)} bytes")
                    return True
            
            print("FAILURE REASON: No result video found in output directory")
        else:
            print(f"FAILURE REASON: SadTalker process failed with code {result.returncode}")
            print(f"STDERR: {result.stderr}")
        
        return False
        
    except subprocess.TimeoutExpired:
        print("FAILURE REASON: SadTalker process timed out after 120 seconds")
        return False
    except Exception as e:
        print(f"FAILURE REASON: Exception occurred: {e}")
        print(f"Exception type: {type(e).__name__}")
        print(f"Exception traceback: {traceback.format_exc()}")
        return False
    finally:
        print(f"=== SADTALKER DETAILED DEBUG END ===")

def create_enhanced_realistic_face(audio_path, video_path, prompt, codec='h264_high', quality='high'):
    """Create enhanced realistic talking face with better lip sync"""
    print("Creating enhanced realistic face...")
    
    # Create a more realistic base face
    img = create_realistic_face()
    
    fps = 30
    duration = 5
    frames = fps * duration
    height, width = img.shape[:2]
    
    # Use better codec for realistic mode
    fourcc = cv2.VideoWriter_fourcc(*'mp4v')
    out = cv2.VideoWriter(video_path, fourcc, fps, (width, height))
    
    if not out.isOpened():
        raise Exception("Failed to open video writer")
    
    try:
        # Enhanced animation with more realistic mouth movements
        for i in range(frames):
            frame = img.copy()
            
            # More sophisticated mouth animation
            time_factor = i / fps
            
            # Multiple frequency components for realistic speech
            mouth_intensity = (
                0.6 * abs(np.sin(time_factor * 8)) +  # Primary speech frequency
                0.3 * abs(np.sin(time_factor * 15)) + # Secondary articulation
                0.1 * abs(np.sin(time_factor * 25))   # Fine details
            )
            
            # Enhanced facial features
            face_center_x, face_center_y = width // 2, height // 2
            
            # More realistic mouth positioning and animation
            mouth_y = int(face_center_y + height * 0.15)
            mouth_x = face_center_x
            mouth_width = int(30 + mouth_intensity * 25)
            mouth_height = int(8 + mouth_intensity * 15)
            
            # Draw animated mouth with more detail
            cv2.ellipse(frame, (mouth_x, mouth_y), (mouth_width, mouth_height), 
                       0, 0, 180, (120, 80, 80), -1)
            
            # Add teeth when mouth is more open
            if mouth_intensity > 0.4:
                teeth_height = int(mouth_height * 0.6)
                cv2.ellipse(frame, (mouth_x, mouth_y - 3), (mouth_width - 8, teeth_height), 
                           0, 0, 180, (240, 240, 240), -1)
            
            # Enhanced eye blinking with more natural timing
            blink_cycle = i % 90
            if blink_cycle < 6:  # More natural blink duration
                eye_y = int(face_center_y - height * 0.08)
                left_eye_x = int(face_center_x - width * 0.12)
                right_eye_x = int(face_center_x + width * 0.12)
                eye_width = int(width * 0.06)
                
                # Animated blink
                blink_intensity = 1 - (blink_cycle / 6)
                eye_height = int(4 * blink_intensity)
                
                cv2.ellipse(frame, (left_eye_x, eye_y), (eye_width, eye_height), 
                           0, 0, 180, (200, 180, 160), -1)
                cv2.ellipse(frame, (right_eye_x, eye_y), (eye_width, eye_height), 
                           0, 0, 180, (200, 180, 160), -1)
            
            # Subtle head movement for realism
            head_sway = int(3 * np.sin(time_factor * 2))
            if head_sway != 0:
                M = np.float32([[1, 0, head_sway], [0, 1, 0]])
                frame = cv2.warpAffine(frame, M, (width, height))
            
            out.write(frame)
        
        print(f"Enhanced realistic face video created: {frames} frames")
        
    finally:
        out.release()

def create_realistic_face():
    """Create a more realistic face image for SadTalker mode"""
    img = np.ones((512, 512, 3), dtype=np.uint8) * 245  # Lighter background
    
    # More realistic face shape and coloring
    face_center = (256, 256)
    face_axes = (140, 160)  # Slightly oval face
    
    # Face with gradient shading
    cv2.ellipse(img, face_center, face_axes, 0, 0, 360, (220, 200, 180), -1)
    
    # Add subtle shading
    cv2.ellipse(img, (face_center[0] - 20, face_center[1] - 20), (120, 140), 0, 0, 360, (210, 190, 170), -1)
    
    # More detailed eyes
    left_eye = (220, 220)
    right_eye = (292, 220)
    
    # Eye whites
    cv2.ellipse(img, left_eye, (18, 12), 0, 0, 360, (255, 255, 255), -1)
    cv2.ellipse(img, right_eye, (18, 12), 0, 0, 360, (255, 255, 255), -1)
    
    # Iris
    cv2.circle(img, left_eye, 8, (100, 150, 200), -1)
    cv2.circle(img, right_eye, 8, (100, 150, 200), -1)
    
    # Pupils
    cv2.circle(img, left_eye, 4, (20, 20, 20), -1)
    cv2.circle(img, right_eye, 4, (20, 20, 20), -1)
    
    # Eye highlights
    cv2.circle(img, (left_eye[0] - 2, left_eye[1] - 2), 2, (255, 255, 255), -1)
    cv2.circle(img, (right_eye[0] - 2, right_eye[1] - 2), 2, (255, 255, 255), -1)
    
    # More realistic nose
    nose_points = np.array([
        [256, 245],
        [250, 260],
        [256, 265],
        [262, 260]
    ], np.int32)
    cv2.fillPoly(img, [nose_points], (200, 180, 160))
    
    # Nostrils
    cv2.ellipse(img, (252, 262), (3, 2), 0, 0, 360, (180, 160, 140), -1)
    cv2.ellipse(img, (260, 262), (3, 2), 0, 0, 360, (180, 160, 140), -1)
    
    # Better eyebrows
    cv2.ellipse(img, (220, 200), (20, 6), 0, 0, 180, (120, 100, 80), -1)
    cv2.ellipse(img, (292, 200), (20, 6), 0, 0, 180, (120, 100, 80), -1)
    
    # Initial mouth (will be animated)
    cv2.ellipse(img, (256, 300), (25, 8), 0, 0, 180, (150, 100, 100), -1)
    
    return img

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
            'codecs': ['h264_high', 'h264_medium', 'h264_fast', 'h265_high', 'webm_high', 'webm_fast'],
            'quality_levels': ['high', 'medium', 'fast'],
            'default_codec': 'webm_fast',
            'ffmpeg_available': check_ffmpeg_available(),
            'disk_space': 'Available',
            'memory': 'Available'
        }
        return jsonify(status)
    except Exception as e:
        return jsonify({'error': str(e)})

def check_ffmpeg_available():
    """Check if FFmpeg is available"""
    try:
        result = subprocess.run(['ffmpeg', '-version'], capture_output=True, text=True)
        return result.returncode == 0
    except:
        return False

@app.route('/debug/logs')
def debug_logs():
    """Debug logs endpoint"""
    try:
        # Return recent logs (placeholder)
        logs = ['Avatar system running', 'TTS initialized', 'MediaPipe ready']
        return jsonify({'logs': logs})
    except Exception as e:
        return jsonify({'error': str(e)})

@app.route('/test-mp4')
def test_mp4():
    """Generate a minimal test MP4 to verify pipeline"""
    try:
        # Create a simple 1-second test video
        test_path = '/app/test_video.mp4'
        cmd = [
            'ffmpeg', '-f', 'lavfi', '-i', 'testsrc=duration=1:size=320x240:rate=30',
            '-f', 'lavfi', '-i', 'sine=frequency=1000:duration=1',
            '-c:v', 'libx264', '-profile:v', 'baseline', '-level', '3.0',
            '-c:a', 'aac', '-pix_fmt', 'yuv420p', '-movflags', '+faststart',
            '-y', test_path
        ]
        
        result = subprocess.run(cmd, capture_output=True, text=True)
        
        if result.returncode == 0 and os.path.exists(test_path):
            return send_file(test_path, mimetype='video/mp4', as_attachment=False)
        else:
            return jsonify({'error': 'Test MP4 generation failed', 'stderr': result.stderr}), 500
            
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/reload')
def reload_config():
    """Force reload configuration"""
    global DEFAULT_CODEC
    DEFAULT_CODEC = 'h264_fast'
    return jsonify({'status': 'reloaded', 'default_codec': DEFAULT_CODEC})

@app.route('/health')
def health():
    return jsonify({
        'status': 'ok', 
        'device': device, 
        'tts_ready': tts is not None,
        'models': 'TTS + MediaPipe',
        'modes': ['simple', 'sadtalker'],
        'codecs': ['h264_high', 'h264_medium', 'h264_fast', 'h265_high', 'webm_high', 'webm_fast'],
        'default_codec': 'h264_fast',
        'endpoints': [
            '/generate?mode=simple&codec=h264_high&quality=high',
            '/generate?mode=sadtalker&codec=h264_medium&quality=medium',
            '/debug/status',
            '/debug/logs',
            '/reload'
        ]
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=7860)