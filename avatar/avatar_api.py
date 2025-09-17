from flask import Flask, request, jsonify, send_file
import os
import subprocess
import tempfile
from pathlib import Path

app = Flask(__name__)

@app.route('/generate', methods=['POST'])
def generate_avatar():
    data = request.json
    text = data.get('text', '')
    image_path = data.get('image', 'examples/source_image/art_0.png')
    
    # Generate TTS audio
    audio_file = tempfile.mktemp(suffix='.wav')
    subprocess.run([
        'python', '-c', 
        f"import pyttsx3; engine = pyttsx3.init(); engine.save_to_file('{text}', '{audio_file}'); engine.runAndWait()"
    ])
    
    # Generate avatar video
    output_file = tempfile.mktemp(suffix='.mp4')
    subprocess.run([
        'python', 'inference.py',
        '--driven_audio', audio_file,
        '--source_image', image_path,
        '--result_dir', os.path.dirname(output_file),
        '--still'
    ])
    
    return send_file(output_file, as_attachment=True)

@app.route('/health')
def health():
    return jsonify({'status': 'ok'})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=7860)