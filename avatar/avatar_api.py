from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import os, subprocess, tempfile, requests, base64, traceback, unicodedata, shutil, wave
from pathlib import Path
from datetime import datetime
import numpy as np, cv2, torch
from pydub import AudioSegment
from moviepy.editor import concatenate_videoclips, VideoFileClip

# ============================================================
# APP INIT
# ============================================================
app = Flask(__name__)
CORS(app)

OLLAMA_HOST = os.getenv('OLLAMA_HOST', 'http://ollama:11434')
TTS_API_URL = os.getenv('TTS_API_URL', 'http://tts:5000/synthesize')

device = "cuda" if torch.cuda.is_available() else "cpu"
DEFAULT_CODEC = 'h264_fast'
ref_image_path = '/app/faces/2.jpg'

# ============================================================
# UTILITIES
# ============================================================
def clean_text(text: str) -> str:
    """Normalize and sanitize input text before TTS"""
    if not text:
        return "Hello"
    text = unicodedata.normalize("NFKC", text).strip()
    text = " ".join(text.split())
    return "".join(ch for ch in text if ch.isprintable())

def get_audio_duration(audio_path):
    with wave.open(audio_path, 'rb') as wf:
        return wf.getnframes() / float(wf.getframerate())

def split_audio(audio_path, chunk_length_s=10):
    audio = AudioSegment.from_file(audio_path)
    chunk_length_ms = chunk_length_s * 1000
    chunks = []
    for i in range(0, len(audio), chunk_length_ms):
        chunk = audio[i:i+chunk_length_ms]
        out_path = f"{audio_path}_chunk{i//chunk_length_ms}.wav"
        chunk.export(out_path, format="wav")
        chunks.append(out_path)
    return chunks

def load_and_preprocess_image(img_input, fallback=ref_image_path):
    img = None
    try:
        if img_input and isinstance(img_input, str):
            if img_input.startswith("http"):
                resp = requests.get(img_input, timeout=10)
                img = cv2.imdecode(np.frombuffer(resp.content, np.uint8), cv2.IMREAD_COLOR)
            elif os.path.exists(img_input):
                img = cv2.imread(img_input)
            else:
                try:
                    img = cv2.imdecode(np.frombuffer(base64.b64decode(img_input), np.uint8), cv2.IMREAD_COLOR)
                except Exception:
                    pass
        if img is None and fallback and os.path.exists(fallback):
            img = cv2.imread(fallback)
        if img is not None:
            img = cv2.resize(img, (512, 512))
            img = cv2.fastNlMeansDenoisingColored(img, None, 10, 10, 7, 21)
        return img
    except Exception as e:
        print(f"Image load error: {e}")
        return cv2.imread(fallback) if os.path.exists(fallback) else None

# ============================================================
# AUDIO + TTS
# ============================================================
def call_tts_service_with_options(text, file_path, tts_engine='espeak', tts_options=None):
    try:
        payload = {'text': text, 'engine': tts_engine}
        if tts_options: payload.update(tts_options)
        response = requests.post(TTS_API_URL, json=payload, timeout=60)
        if response.status_code == 200:
            with open(file_path, 'wb') as f:
                f.write(response.content)
            return True
        print(f"TTS error {response.status_code}: {response.text[:200]}")
        return False
    except Exception as e:
        print(f"TTS call failed: {e}")
        return False

def normalize_audio(audio_path):
    fixed_path = audio_path.replace('.wav', '_fixed.wav')
    cmd = ["ffmpeg","-y","-i",audio_path,"-ac","1","-ar","16000","-acodec","pcm_s16le",fixed_path]
    subprocess.run(cmd, check=True)
    return fixed_path

# ============================================================
# SADTALKER VIDEO
# ============================================================
def generate_sadtalker_video(audio_path, video_path, prompt, codec, quality,
                             timeout=120, enhancer="gfpgan",
                             split_chunks=False, chunk_length=10,
                             source_image="/app/faces/2.jpg"):

    result_dir = "/app/static/sadtalker_output"
    os.makedirs(result_dir, exist_ok=True)
    video_parts = []

    try:
        chunks = split_audio(audio_path, chunk_length) if split_chunks else [audio_path]
        for idx, chunk_path in enumerate(chunks):
            chunk_result_dir = os.path.join(result_dir, f"chunk_{idx}")
            os.makedirs(chunk_result_dir, exist_ok=True)
            cmd = [
                "python", "/app/SadTalker/inference.py",
                "--driven_audio", chunk_path,
                "--source_image", source_image,
                "--result_dir", chunk_result_dir,
                "--still", "--preprocess", "crop", "--enhancer", enhancer
            ]
            try:
                subprocess.run(cmd, timeout=timeout, check=True)
            except Exception as e:
                print(f"SadTalker failed: {e}")
                return False
            avi_files = [f for f in os.listdir(chunk_result_dir) if f.endswith(".avi")]
            if not avi_files:
                return False
            video_parts.append(os.path.join(chunk_result_dir, avi_files[0]))

        if len(video_parts) > 1:
            clips = [VideoFileClip(v) for v in video_parts]
            final_clip = concatenate_videoclips(clips)
            final_clip.write_videofile(video_path, codec="libx264", audio_codec="aac")
            for c in clips: c.close()
        else:
            shutil.move(video_parts[0], video_path)
        return True
    finally:
        if split_chunks:
            for idx in range(len(video_parts)):
                shutil.rmtree(os.path.join(result_dir, f"chunk_{idx}"), ignore_errors=True)

# ============================================================
# FALLBACK FACE GENERATION
# ============================================================
def generate_talking_face(image_path, audio_path, output_path, codec=None, quality=None):
    try:
        import mediapipe as mp
        mp_face_detection = mp.solutions.face_detection
        img = cv2.imread(image_path) if os.path.exists(image_path) else create_default_face()
        with mp_face_detection.FaceDetection(model_selection=0, min_detection_confidence=0.5) as fd:
            results = fd.process(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))
            if results.detections:
                create_animated_face(img, results.detections[0], audio_path, output_path)
            else:
                create_basic_avatar(audio_path, output_path, "No face detected")
    except Exception as e:
        print(f"Face generation error: {str(e)}")
        create_basic_avatar(audio_path, output_path, "Face animation failed")

# (create_default_face, create_animated_face, create_basic_avatar definitions remain as in your draft)

# ============================================================
# VIDEO CONVERSION
# ============================================================
def convert_video_with_codec(video_path, audio_path, codec, quality):
    codec_configs = {
        'h264_fast': {'video_codec':'libx264','audio_codec':'aac','preset':'ultrafast','crf':'28','profile':'baseline','level':'3.1','extension':'.mp4'},
        'h264_high': {'video_codec':'libx264','audio_codec':'aac','preset':'ultrafast','crf':'23','profile':'baseline','level':'3.0','extension':'.mp4'},
        'h265_high': {'video_codec':'libx265','audio_codec':'aac','preset':'slow','crf':'20','extension':'.mp4'},
        'webm_fast': {'video_codec':'libvpx','audio_codec':'libvorbis','crf':'30','b:v':'500k','b:a':'96k','cpu-used':'5','deadline':'realtime','extension':'.webm'},
    }
    config = codec_configs.get(codec, codec_configs['h264_high'])
    output_path = video_path.replace('.avi', config['extension'])
    cmd = ['ffmpeg','-i',video_path,'-i',audio_path,'-c:v',config['video_codec'],'-c:a',config['audio_codec'],'-y',output_path]
    subprocess.run(cmd)
    return output_path if os.path.exists(output_path) else None

# ============================================================
# ROUTES
# ============================================================
@app.route('/generate', methods=['POST'])
def generate_avatar():
    mode = request.args.get('mode', 'simple')
    codec = request.args.get('codec', DEFAULT_CODEC)
    quality = request.args.get('quality', 'high')
    try:
        data = request.json
        if not data:
            return jsonify({'error': 'No JSON data provided'}), 400
        prompt = clean_text(data.get('prompt', 'Hello'))
        source_image_input = data.get('image', ref_image_path)
        tts_engine = data.get('tts_engine', 'espeak')
        tts_options = data.get('tts_options', {})
        sadtalker_options = {
            "timeout": int(data.get("timeout", 120)),
            "enhancer": data.get("enhancer", "gfpgan"),
            "split_chunks": bool(data.get("split_chunks", False)),
            "chunk_length": int(data.get("chunk_length", 10))
        }
        img = load_and_preprocess_image(source_image_input)
        if img is None:
            return jsonify({'error': 'Image load failed'}), 500
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as audio_file:
            audio_path = audio_file.name
        video_path = '/app/static/avatar_video.avi'
        os.makedirs('/app/static', exist_ok=True)
        if not call_tts_service_with_options(prompt, audio_path, tts_engine, tts_options):
            return jsonify({'error': 'TTS failed'}), 500
        audio_path = normalize_audio(audio_path)
        if mode == 'sadtalker':
            success = generate_sadtalker_video(audio_path, video_path, prompt, codec, quality, **sadtalker_options, source_image=source_image_input)
            if not success:
                generate_talking_face(source_image_input, audio_path, video_path, codec, quality)
        else:
            generate_talking_face(source_image_input, audio_path, video_path, codec, quality)
        final_path = convert_video_with_codec(video_path, audio_path, codec, quality)
        return send_file(final_path, mimetype='video/mp4') if final_path else jsonify({'error':'Video creation failed'}), 500
    except Exception as e:
        print(traceback.format_exc())
        return jsonify({'error': str(e)}), 500

@app.route('/debug/status')
def debug_status():
    return jsonify({
        'timestamp': str(datetime.now()),
        'device': device,
        'tts_ready': os.getenv('TTS_API_URL') is not None,
        'sadtalker_installed': os.path.exists('/app/SadTalker'),
        'modes': ['simple','sadtalker'],
        'codecs': ['h264_high','h264_medium','h264_fast','h265_high','webm_high','webm_fast'],
        'default_codec': DEFAULT_CODEC
    })

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8000, debug=True)
