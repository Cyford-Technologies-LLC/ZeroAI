from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import os
import tempfile
import torch
import subprocess
import requests
from TTS.api import TTS

app = Flask(__name__)
CORS(app)

# Initialize TTS
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
        print(f"Generating realistic avatar for: {prompt}")
        
        # Create temp files
        audio_path = tempfile.mktemp(suffix='.wav')
        video_path = tempfile.mktemp(suffix='.mp4')
        
        try:
            # Generate TTS
            if tts:
                print("Generating TTS...")
                tts.tts_to_file(text=prompt, file_path=audio_path)
                print("TTS completed")
            
            # Use SadTalker for realistic talking head
            print("Generating realistic talking head...")
            success = generate_sadtalker_video(audio_path, video_path)
            
            if success and os.path.exists(video_path) and os.path.getsize(video_path) > 0:
                print(f"Realistic avatar created: {os.path.getsize(video_path)} bytes")
                return send_file(video_path, mimetype='video/mp4', as_attachment=False)
            else:
                return jsonify({'error': 'Realistic avatar generation failed'}), 500
                
        finally:
            # Cleanup
            try:
                if os.path.exists(audio_path):
                    os.unlink(audio_path)
            except:
                pass
        
    except Exception as e:
        print(f"Avatar generation error: {str(e)}")
        return jsonify({'error': str(e)}), 500

def generate_sadtalker_video(audio_path, output_path):
    """Generate realistic talking head using SadTalker"""
    try:
        # Download SadTalker if not exists
        setup_sadtalker()
        
        # Use a high-quality reference image
        ref_image = "/app/reference_person.jpg"
        if not os.path.exists(ref_image):
            download_reference_image(ref_image)
        
        # Run SadTalker
        cmd = [
            "python", "/app/SadTalker/inference.py",
            "--driven_audio", audio_path,
            "--source_image", ref_image,
            "--result_dir", "/app/results",
            "--still",
            "--preprocess", "full",
            "--enhancer", "gfpgan"
        ]
        
        result = subprocess.run(cmd, capture_output=True, text=True, cwd="/app/SadTalker")
        
        if result.returncode == 0:
            # Find the generated video
            result_files = []
            for root, dirs, files in os.walk("/app/results"):
                for file in files:
                    if file.endswith('.mp4'):
                        result_files.append(os.path.join(root, file))
            
            if result_files:
                # Copy the latest result
                latest_result = max(result_files, key=os.path.getctime)
                subprocess.run(['cp', latest_result, output_path])
                return True
        
        print(f"SadTalker failed: {result.stderr}")
        return False
        
    except Exception as e:
        print(f"SadTalker error: {e}")
        return False

def setup_sadtalker():
    """Setup SadTalker if not already installed"""
    if not os.path.exists("/app/SadTalker"):
        print("Installing SadTalker...")
        subprocess.run([
            "git", "clone", "https://github.com/OpenTalker/SadTalker.git", "/app/SadTalker"
        ])
        
        # Download checkpoints
        subprocess.run([
            "bash", "/app/SadTalker/scripts/download_models.sh"
        ], cwd="/app/SadTalker")

def download_reference_image(path):
    """Download a high-quality reference person image"""
    try:
        # Use a professional headshot
        urls = [
            "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=512&h=512&fit=crop&crop=face",
            "https://images.unsplash.com/photo-1494790108755-2616b612b786?w=512&h=512&fit=crop&crop=face",
            "https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=512&h=512&fit=crop&crop=face"
        ]
        
        for url in urls:
            try:
                response = requests.get(url, timeout=10)
                if response.status_code == 200:
                    with open(path, 'wb') as f:
                        f.write(response.content)
                    print(f"Downloaded reference image: {path}")
                    return True
            except:
                continue
                
        print("Failed to download reference image")
        return False
        
    except Exception as e:
        print(f"Reference image download error: {e}")
        return False

@app.route('/health')
def health():
    return jsonify({
        'status': 'ok',
        'device': device,
        'tts_ready': tts is not None,
        'models': 'SadTalker + TTS'
    })

if __name__ == '__main__':
    print("Starting SadTalker Avatar API...")
    app.run(host='0.0.0.0', port=7860)