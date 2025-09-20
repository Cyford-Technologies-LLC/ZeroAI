from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import os
import subprocess
import tempfile
import requests
import torch
import numpy as np
from TTS.api import TTS
import cv2
import traceback
import logging
import json
from datetime import datetime

app = Flask(__name__)
CORS(app)

# Setup comprehensive logging
logging.basicConfig(
    level=logging.DEBUG,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/app/avatar_debug.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Initialize TTS
device = "cuda" if torch.cuda.is_available() else "cpu"
logger.info(f"Initializing on device: {device}")

try:
    tts = TTS("tts_models/en/ljspeech/tacotron2-DDC_ph", gpu=(device=="cuda"))
    logger.info("TTS initialized successfully")
except Exception as e:
    logger.error(f"TTS initialization failed: {e}")
    tts = None

@app.route('/generate', methods=['POST'])
def generate_avatar():
    start_time = datetime.now()
    mode = request.args.get('mode', 'simple')
    
    logger.info(f"=== AVATAR GENERATION START ===")
    logger.info(f"Mode: {mode}")
    logger.info(f"Request headers: {dict(request.headers)}")
    
    try:
        data = request.json
        if not data:
            logger.error("No JSON data provided")
            return jsonify({'error': 'No JSON data provided'}), 400
            
        prompt = data.get('prompt', 'Hello')
        logger.info(f"Prompt: {prompt}")
        
        # Create temp files
        audio_path = tempfile.mktemp(suffix='.wav')
        video_path = tempfile.mktemp(suffix='.mp4')
        
        logger.info(f"Audio path: {audio_path}")
        logger.info(f"Video path: {video_path}")
        
        try:
            # Generate TTS
            logger.info("Starting TTS generation...")
            if tts:
                tts.tts_to_file(text=prompt, file_path=audio_path)
                logger.info(f"TTS completed. Audio file size: {os.path.getsize(audio_path)} bytes")
            else:
                logger.error("TTS not available")
                return jsonify({'error': 'TTS not available'}), 500
            
            # Generate video based on mode
            if mode == 'sadtalker':
                logger.info("Using SadTalker mode")
                success = generate_sadtalker_video(audio_path, video_path, prompt)
            else:
                logger.info("Using simple mode")
                success = generate_simple_video(audio_path, video_path, prompt)
            
            if success and os.path.exists(video_path) and os.path.getsize(video_path) > 0:
                duration = (datetime.now() - start_time).total_seconds()
                file_size = os.path.getsize(video_path)
                logger.info(f"Avatar generation successful!")
                logger.info(f"Duration: {duration}s")
                logger.info(f"Video size: {file_size} bytes")
                logger.info("=== AVATAR GENERATION END ===")
                
                return send_file(video_path, mimetype='video/mp4', as_attachment=False)
            else:
                logger.error("Video generation failed")
                return jsonify({'error': f'{mode} avatar generation failed'}), 500
                
        finally:
            # Cleanup
            try:
                if os.path.exists(audio_path):
                    os.unlink(audio_path)
                    logger.debug(f"Cleaned up audio file: {audio_path}")
            except Exception as e:
                logger.warning(f"Failed to cleanup audio: {e}")
        
    except Exception as e:
        logger.error(f"Avatar generation error: {str(e)}")
        logger.error(f"Traceback: {traceback.format_exc()}")
        return jsonify({'error': str(e), 'traceback': traceback.format_exc()}), 500

def generate_simple_video(audio_path, video_path, prompt):
    """Generate simple OpenCV avatar"""
    logger.info("=== SIMPLE AVATAR GENERATION ===")
    
    try:
        fps = 30
        duration = 3
        frames = fps * duration
        
        logger.info(f"Creating video: {frames} frames at {fps} fps")
        
        # Try multiple codecs
        codecs = ['mp4v', 'XVID', 'MJPG']
        out = None
        
        for codec in codecs:
            try:
                fourcc = cv2.VideoWriter_fourcc(*codec)
                out = cv2.VideoWriter(video_path, fourcc, fps, (640, 480))
                if out.isOpened():
                    logger.info(f"Using codec: {codec}")
                    break
                else:
                    if out:
                        out.release()
                    out = None
            except Exception as e:
                logger.warning(f"Codec {codec} failed: {e}")
                continue
        
        if out is None:
            logger.error("No working codec found!")
            return False
        
        try:
            for i in range(frames):
                frame = np.zeros((480, 640, 3), dtype=np.uint8)
                frame.fill(30)
                
                # Draw realistic face
                cv2.circle(frame, (320, 240), 100, (220, 200, 180), -1)  # Face
                cv2.circle(frame, (295, 215), 12, (80, 60, 40), -1)  # Left eye
                cv2.circle(frame, (345, 215), 12, (80, 60, 40), -1)  # Right eye
                cv2.circle(frame, (297, 213), 4, (255, 255, 255), -1)  # Left eye highlight
                cv2.circle(frame, (347, 213), 4, (255, 255, 255), -1)  # Right eye highlight
                
                # Nose
                cv2.ellipse(frame, (320, 245), (8, 12), 0, 0, 180, (200, 180, 160), -1)
                
                # Animated mouth
                mouth_open = 5 + int(15 * abs(np.sin(i * 0.3)) * abs(np.sin(i * 0.1)))
                cv2.ellipse(frame, (320, 275), (25, mouth_open), 0, 0, 180, (100, 80, 80), -1)
                
                # Eyebrows
                cv2.ellipse(frame, (295, 195), (15, 5), 0, 0, 180, (120, 100, 80), -1)
                cv2.ellipse(frame, (345, 195), (15, 5), 0, 0, 180, (120, 100, 80), -1)
                
                cv2.putText(frame, prompt[:30], (50, 50), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (255, 255, 255), 2)
                
                out.write(frame)
            
            logger.info("Simple video frames created")
            
        finally:
            if out:
                out.release()
        
        # Add audio using FFmpeg
        temp_video = video_path + "_temp.mp4"
        os.rename(video_path, temp_video)
        
        cmd = [
            'ffmpeg', '-i', temp_video, '-i', audio_path,
            '-c:v', 'libx264', '-c:a', 'aac', '-preset', 'ultrafast',
            '-shortest', '-y', video_path
        ]
        
        logger.info(f"Adding audio with FFmpeg: {' '.join(cmd)}")
        result = subprocess.run(cmd, capture_output=True, text=True)
        
        if result.returncode == 0:
            logger.info("Audio added successfully")
            os.unlink(temp_video)
            return True
        else:
            logger.error(f"FFmpeg failed: {result.stderr}")
            if os.path.exists(temp_video):
                os.rename(temp_video, video_path)
            return True  # Return video without audio as fallback
            
    except Exception as e:
        logger.error(f"Simple video generation error: {e}")
        logger.error(traceback.format_exc())
        return False

def generate_sadtalker_video(audio_path, video_path, prompt):
    """Generate SadTalker realistic avatar"""
    logger.info("=== SADTALKER AVATAR GENERATION ===")
    
    try:
        # Setup SadTalker
        if not setup_sadtalker():
            logger.error("SadTalker setup failed")
            return False
        
        # Get reference image
        ref_image = "/app/reference_person.jpg"
        if not os.path.exists(ref_image):
            logger.info("Downloading reference image...")
            if not download_reference_image(ref_image):
                logger.error("Failed to get reference image")
                return False
        
        logger.info(f"Using reference image: {ref_image}")
        
        # Run SadTalker
        cmd = [
            "python", "/app/SadTalker/inference.py",
            "--driven_audio", audio_path,
            "--source_image", ref_image,
            "--result_dir", "/app/sadtalker_results",
            "--still",
            "--preprocess", "full",
            "--enhancer", "gfpgan"
        ]
        
        logger.info(f"Running SadTalker: {' '.join(cmd)}")
        
        result = subprocess.run(cmd, capture_output=True, text=True, cwd="/app/SadTalker")
        
        logger.info(f"SadTalker stdout: {result.stdout}")
        if result.stderr:
            logger.warning(f"SadTalker stderr: {result.stderr}")
        
        if result.returncode == 0:
            # Find generated video
            result_files = []
            for root, dirs, files in os.walk("/app/sadtalker_results"):
                for file in files:
                    if file.endswith('.mp4'):
                        result_files.append(os.path.join(root, file))
            
            if result_files:
                latest_result = max(result_files, key=os.path.getctime)
                logger.info(f"Found SadTalker result: {latest_result}")
                subprocess.run(['cp', latest_result, video_path])
                return True
            else:
                logger.error("No SadTalker output files found")
        else:
            logger.error(f"SadTalker failed with return code: {result.returncode}")
        
        return False
        
    except Exception as e:
        logger.error(f"SadTalker generation error: {e}")
        logger.error(traceback.format_exc())
        return False

def setup_sadtalker():
    """Setup SadTalker if not installed"""
    try:
        if not os.path.exists("/app/SadTalker"):
            logger.info("Cloning SadTalker repository...")
            result = subprocess.run([
                "git", "clone", "https://github.com/OpenTalker/SadTalker.git", "/app/SadTalker"
            ], capture_output=True, text=True)
            
            if result.returncode != 0:
                logger.error(f"Git clone failed: {result.stderr}")
                return False
            
            logger.info("SadTalker cloned successfully")
        
        # Check for checkpoints
        checkpoint_dir = "/app/SadTalker/checkpoints"
        if not os.path.exists(checkpoint_dir) or len(os.listdir(checkpoint_dir)) == 0:
            logger.info("Downloading SadTalker checkpoints...")
            result = subprocess.run([
                "bash", "/app/SadTalker/scripts/download_models.sh"
            ], cwd="/app/SadTalker", capture_output=True, text=True)
            
            if result.returncode != 0:
                logger.error(f"Checkpoint download failed: {result.stderr}")
                return False
            
            logger.info("SadTalker checkpoints downloaded")
        
        return True
        
    except Exception as e:
        logger.error(f"SadTalker setup error: {e}")
        return False

def download_reference_image(path):
    """Download reference person image"""
    try:
        urls = [
            "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=512&h=512&fit=crop&crop=face",
            "https://images.unsplash.com/photo-1494790108755-2616b612b786?w=512&h=512&fit=crop&crop=face",
            "https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=512&h=512&fit=crop&crop=face"
        ]
        
        for i, url in enumerate(urls):
            try:
                logger.info(f"Trying to download from URL {i+1}: {url}")
                response = requests.get(url, timeout=10)
                if response.status_code == 200:
                    with open(path, 'wb') as f:
                        f.write(response.content)
                    logger.info(f"Downloaded reference image: {path} ({len(response.content)} bytes)")
                    return True
            except Exception as e:
                logger.warning(f"URL {i+1} failed: {e}")
                continue
        
        logger.error("All reference image downloads failed")
        return False
        
    except Exception as e:
        logger.error(f"Reference image download error: {e}")
        return False

@app.route('/debug/logs')
def get_logs():
    """Get debug logs"""
    try:
        if os.path.exists('/app/avatar_debug.log'):
            with open('/app/avatar_debug.log', 'r') as f:
                logs = f.readlines()[-100:]  # Last 100 lines
            return jsonify({'logs': logs})
        else:
            return jsonify({'logs': ['No log file found']})
    except Exception as e:
        return jsonify({'error': str(e)})

@app.route('/debug/status')
def get_status():
    """Get system status"""
    try:
        status = {
            'timestamp': datetime.now().isoformat(),
            'device': device,
            'tts_ready': tts is not None,
            'sadtalker_installed': os.path.exists('/app/SadTalker'),
            'sadtalker_checkpoints': os.path.exists('/app/SadTalker/checkpoints') and len(os.listdir('/app/SadTalker/checkpoints')) > 0 if os.path.exists('/app/SadTalker/checkpoints') else False,
            'reference_image': os.path.exists('/app/reference_person.jpg'),
            'disk_space': subprocess.run(['df', '-h', '/app'], capture_output=True, text=True).stdout,
            'memory': subprocess.run(['free', '-h'], capture_output=True, text=True).stdout
        }
        return jsonify(status)
    except Exception as e:
        return jsonify({'error': str(e)})

@app.route('/health')
def health():
    return jsonify({
        'status': 'ok',
        'device': device,
        'tts_ready': tts is not None,
        'modes': ['simple', 'sadtalker'],
        'endpoints': ['/generate?mode=simple', '/generate?mode=sadtalker', '/debug/logs', '/debug/status']
    })

if __name__ == '__main__':
    logger.info("Starting Dual-Mode Avatar API...")
    logger.info(f"Device: {device}")
    logger.info(f"TTS Ready: {tts is not None}")
    app.run(host='0.0.0.0', port=7860, debug=True)