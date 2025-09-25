from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import os, subprocess, tempfile, requests, base64, traceback, unicodedata , shutil , glob , time
from pydub import AudioSegment
import mediapipe as mp
from pathlib import Path
import torch, numpy as np, cv2
import traceback
from datetime import datetime

import wave
from pydub import AudioSegment
from moviepy.editor import concatenate_videoclips, VideoFileClip

import logging, traceback



app = Flask(__name__)
CORS(app)


@app.errorhandler(Exception)
def handle_exception(e):
    logging.error("Unhandled Exception: %s\n%s", e, traceback.format_exc())
    return {"error": str(e)}, 500

def get_audio_duration(audio_path):
    with wave.open(audio_path, 'rb') as wf:
        frames = wf.getnframes()
        rate = wf.getframerate()
        duration = frames / float(rate)
    return duration

def split_audio(audio_path, chunk_length_s=10):
    """Split audio into chunks of chunk_length_s seconds"""
    audio = AudioSegment.from_file(audio_path)
    chunk_length_ms = chunk_length_s * 1000
    chunks = []
    for i in range(0, len(audio), chunk_length_ms):
        chunk = audio[i:i+chunk_length_ms]
        out_path = f"{audio_path}_chunk{i//chunk_length_ms}.wav"
        chunk.export(out_path, format="wav")
        chunks.append(out_path)
    return chunks

def concat_videos(video_list, output_path):
    """Concat video files using ffmpeg"""
    list_file = "/tmp/concat_list.txt"
    with open(list_file, "w") as f:
        for v in video_list:
            f.write(f"file '{v}'\n")
    cmd = ["ffmpeg", "-y", "-f", "concat", "-safe", "0", "-i", list_file, "-c", "copy", output_path]
    subprocess.run(cmd, check=True)
    return output_path





OLLAMA_HOST = os.getenv('OLLAMA_HOST', 'http://ollama:11434')
TTS_API_URL = os.getenv('TTS_API_URL', 'http://tts:5000/synthesize')

device = "cuda" if torch.cuda.is_available() else "cpu"
DEFAULT_CODEC = 'h264_fast'
ref_image_path = '/app/faces/2.jpg'

############################
# TEXT CLEANING
############################
def clean_text(text: str) -> str:
    """Normalize and sanitize input text before TTS"""
    if not text:
        return "Hello"
    text = unicodedata.normalize("NFKC", text)  # normalize Unicode
    text = text.strip()
    text = " ".join(text.split())  # collapse multiple spaces/newlines
    # Remove any control chars (except common punctuation)
    text = "".join(ch for ch in text if ch.isprintable())
    return text

############################
# IMAGE PROCESSING
############################
def load_and_preprocess_image(img_input, fallback=ref_image_path):
    """Load image from path, base64, or URL, then preprocess for face detection"""
    img = None
    try:
        if img_input and isinstance(img_input, str):
            if img_input.startswith("http"):
                resp = requests.get(img_input, timeout=10)
                img_arr = np.frombuffer(resp.content, np.uint8)
                img = cv2.imdecode(img_arr, cv2.IMREAD_COLOR)
            elif os.path.exists(img_input):
                img = cv2.imread(img_input)
            else:
                # Try base64
                try:
                    img_data = base64.b64decode(img_input)
                    img_arr = np.frombuffer(img_data, np.uint8)
                    img = cv2.imdecode(img_arr, cv2.IMREAD_COLOR)
                except Exception:
                    pass
        # fallback
        if img is None and fallback and os.path.exists(fallback):
            img = cv2.imread(fallback)

        if img is not None:
            # Resize for consistency
            img = cv2.resize(img, (512, 512))
            # Optional: denoise / normalize
            img = cv2.fastNlMeansDenoisingColored(img, None, 10, 10, 7, 21)
        return img
    except Exception as e:
        print(f"Image load error: {e}")
        return cv2.imread(fallback) if os.path.exists(fallback) else None

############################
# TTS SERVICE
############################
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

############################
# MAIN ROUTE
############################
@app.route('/generate', methods=['POST'])
def generate_avatar():
    mode = request.args.get('mode', 'simple')
    codec = request.args.get('codec', DEFAULT_CODEC)
    quality = request.args.get('quality', 'high')

    try:
        data = request.json
        if not data:
            return jsonify({'error': 'No JSON data provided'}), 400

        # Prompt cleanup
        prompt = clean_text(data.get('prompt', 'Hello'))

        # Image handling
        source_image_input = data.get('image', ref_image_path)

        # Codec / TTS
        codec_options = data.get('codec_options', {})
        tts_engine = data.get('tts_engine', 'espeak')
        tts_options = data.get('tts_options', {})

        # SadTalker options (exposed to API)
        sadtalker_options = {
            "timeout": int(data.get("timeout", 1200)),
            "enhancer": data.get("enhancer", "gfpgan"),
            "split_chunks": bool(data.get("split_chunks", False)),
            "chunk_length": int(data.get("chunk_length", 10))
        }

        print(f"=== AVATAR GENERATION START ===")
        print(f"Mode: {mode}, Codec: {codec}, Quality: {quality}")
        print(f"Prompt: {prompt[:50]}...")
        print(f"Image input: {str(source_image_input)[:80]}")
        print(f"TTS Engine: {tts_engine}, Options: {tts_options}")
        print(f"Codec options: {codec_options}")
        print(f"SadTalker options: {sadtalker_options}")

        # Load and preprocess image
        img = load_and_preprocess_image(source_image_input)
        if img is None:
            return jsonify({'error': 'Image load failed'}), 500

        # Temp files
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as audio_file:
            audio_path = audio_file.name
        video_path = '/app/static/avatar_video.avi'
        os.makedirs('/app/static', exist_ok=True)

        try:
            # TTS generation
            if not call_tts_service_with_options(prompt, audio_path, tts_engine, tts_options):
                return jsonify({'error': 'TTS failed'}), 500
            audio_path = normalize_audio(audio_path)

            # Video generation
            if mode == 'sadtalker':
                print("=== ATTEMPTING SADTALKER MODE ===")
                success = generate_sadtalker_video(
                    audio_path,
                    video_path,
                    prompt,
                    codec,
                    quality,
                    timeout=sadtalker_options["timeout"],
                    enhancer=sadtalker_options["enhancer"],
                    split_chunks=sadtalker_options["split_chunks"],
                    chunk_length=sadtalker_options["chunk_length"],
                    source_image=source_image_input
                )

                if not success:
                    print("=== SADTALKER FAILED - FALLBACK TO SIMPLE FACE ===")
                    generate_talking_face(data.get('image', '/app/default_face.jpg'), audio_path, video_path, codec, quality)
            else:
                print("=== USING SIMPLE/MEDIAPIPE MODE ===")
                generate_talking_face(data.get('image', '/app/default_face.jpg'), audio_path, video_path, codec, quality)

            # Codec conversion
            fallback_path = convert_video_with_codec(video_path, audio_path, codec, quality)
            if fallback_path and os.path.exists(fallback_path):
                return send_file(fallback_path, mimetype='video/mp4', as_attachment=False)

            return jsonify({'error': 'Video creation failed'}), 500
        finally:
            try:
                if os.path.exists(audio_path):
                    os.unlink(audio_path)
            except:
                pass

    except Exception as e:
        print(f"Error: {e}")
        print(traceback.format_exc())
        return jsonify({'error': str(e)}), 500








def generate_sadtalker_video(audio_path, video_path, prompt, codec, quality,
                             timeout=1200, enhancer="gfpgan",
                             split_chunks=False, chunk_length=10,
                             source_image="/app/faces/2.jpg"):
    """
    Run SadTalker over the provided audio (optionally split into chunks for long audio),
    scale timeouts based on audio duration, gather produced videos and concatenate
    into video_path. Returns True on success, False on error.
    """

    result_dir = "/app/static/sadtalker_output"
    os.makedirs(result_dir, exist_ok=True)

    video_parts = []
    chunks = []  # ensure variable exists for finally/cleanup

    try:
        # --- Auto adjust timeout & chunking ---
        duration = get_audio_duration(audio_path)
        print(f"Audio duration detected: {duration:.2f}s")

        # Increase provided timeout to at least ~3x audio length so SadTalker has enough time.
        # This gives a safety multiplier for processing overhead.
        # timeout = max(timeout, int(duration * 3))
        timeout = calculate_timeout(duration, source_image="/app/faces/2.jpg", test_audio="/app/test_audio.wav")

        print(f"Base adjusted timeout (will be used as minimum per-chunk): {timeout}s")

        # Auto-enable chunking if audio > 80s and user didn't explicitly request chunking
        if duration > 80 and not split_chunks:
            print("Audio longer than 80s — enabling automatic chunking")
            split_chunks = True

        # Validate chunk_length
        if chunk_length <= 0:
            chunk_length = 10

        # Split if requested
        if split_chunks:
            print(f"=== Splitting audio into {chunk_length}s chunks ===")
            chunks = split_audio(audio_path, chunk_length)
            if not chunks:
                print("Split produced no chunks, aborting.")
                return False
        else:
            chunks = [audio_path]

        # --- Process each chunk with SadTalker ---
        for idx, chunk_path in enumerate(chunks):
            print(f"=== Processing chunk {idx+1}/{len(chunks)} ===")

            chunk_result_dir = os.path.join(result_dir, f"chunk_{idx}")
            os.makedirs(chunk_result_dir, exist_ok=True)

            cmd = [
                "python", "/app/SadTalker/inference.py",
                "--driven_audio", chunk_path,
                "--source_image", source_image,
                "--result_dir", chunk_result_dir,
                "--still", "--preprocess", "crop", "--enhancer", enhancer
            ]

            # Compute a per-chunk timeout based on the chunk audio length (safer than global-only)
            try:
                chunk_duration = get_audio_duration(chunk_path)
            except Exception:
                chunk_duration = duration if idx == 0 else chunk_length
            # give chunk plenty of time: at least `timeout`, otherwise chunk_duration*3 + buffer
            # per_chunk_timeout = max(timeout, int(chunk_duration * 3) + 30)
            per_chunk_timeout = calculate_timeout(
                chunk_duration,
                source_image=source_image,  # pass in your avatar image
                test_audio="/app/test_audio.wav"
            )
            print(f"Running SadTalker for chunk {idx+1}. chunk_duration={chunk_duration:.2f}s per_chunk_timeout={per_chunk_timeout}s")
            print("SadTalker command:", " ".join(cmd))

            try:
                subprocess.run(cmd, timeout=per_chunk_timeout, check=True)
            except subprocess.TimeoutExpired:
                print(f"Chunk {idx+1} timed out after {per_chunk_timeout}s")
                return False
            except subprocess.CalledProcessError as e:
                print(f"SadTalker failed on chunk {idx+1}: {e}")
                return False

            # Search recursively for video output (avi or mp4). SadTalker may put file in nested folder.
            video_candidates = glob.glob(os.path.join(chunk_result_dir, "**", "*.avi"), recursive=True)
            video_candidates += glob.glob(os.path.join(chunk_result_dir, "**", "*.mp4"), recursive=True)

            if not video_candidates:
                print(f"No video file found in {chunk_result_dir}")
                return False

            # Choose the newest produced file for this chunk
            best_file = max(video_candidates, key=os.path.getmtime)
            print(f"Found output video for chunk {idx+1}: {best_file}")
            video_parts.append(best_file)

        # --- Merge chunk videos (if multiple) or copy single ---
        if len(video_parts) > 1:
            print("=== Concatenating chunk videos ===")
            # Use ffmpeg filter_complex concat to avoid pitfalls with concat text quoting.
            # This will re-encode but is robust across arbitrary filenames and formats.
            concat_cmd = ["ffmpeg", "-y"]
            for p in video_parts:
                concat_cmd.extend(["-i", p])

            # Build concat filter: [0:v:0][0:a:0][1:v:0][1:a:0]...concat=n=X:v=1:a=1[outv][outa]
            input_pairs = "".join(f"[{i}:v:0][{i}:a:0]" for i in range(len(video_parts)))
            filter_str = f"{input_pairs}concat=n={len(video_parts)}:v=1:a=1[outv][outa]"

            concat_cmd.extend([
                "-filter_complex", filter_str,
                "-map", "[outv]", "-map", "[outa]",
                # encode quickly (ultrafast) to keep speed reasonable
                "-c:v", "libx264", "-preset", "ultrafast", "-crf", "28",
                "-c:a", "aac", "-b:a", "64k",
                video_path
            ])
            print("Running ffmpeg concat (re-encode):", " ".join(concat_cmd))
            subprocess.run(concat_cmd, check=True)
        else:
            # Single chunk: copy produced file to final path
            print("=== Single video part, copying to final path ===")
            shutil.copy(video_parts[0], video_path)

        print("SadTalker video generation completed successfully.")
        return True

    finally:
        # Cleanup: remove chunk dirs and any chunk audio files produced by split_audio
        if split_chunks:
            # remove chunk result directories and chunk audio files
            for idx in range(len(chunks)):
                chunk_result_dir = os.path.join(result_dir, f"chunk_{idx}")
                if os.path.exists(chunk_result_dir):
                    try:
                        shutil.rmtree(chunk_result_dir, ignore_errors=True)
                    except Exception as e:
                        print(f"Failed removing chunk_result_dir {chunk_result_dir}: {e}")

                # expected split_audio naming: original.wav_chunk{n}.wav
                try:
                    candidate = f"{audio_path}_chunk{idx}.wav"
                    if os.path.exists(candidate):
                        os.remove(candidate)
                except Exception:
                    pass













































def generate_talking_face(image_path, audio_path, output_path, codec=None, quality=None):
    """Generate realistic talking face using MediaPipe."""
    try:
        print("Starting face detection...")
        mp_face_detection = mp.solutions.face_detection

        # Load source image or create default
        if os.path.exists(image_path):
            img = cv2.imread(image_path)
            print(f"Loaded image: {image_path}")
        else:
            print("Creating default face image...")
            img = create_default_face()

        with mp_face_detection.FaceDetection(model_selection=0,
                                             min_detection_confidence=0.5) as face_detection:
            results = face_detection.process(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))

            if results.detections:
                print(f"Found {len(results.detections)} faces")
                # convert the mediapipe detection into integer pixel bbox first
                detection = results.detections[0]
                h_img, w_img = img.shape[:2]
                rbb = detection.location_data.relative_bounding_box
                x = int(rbb.xmin * w_img)
                y = int(rbb.ymin * h_img)
                w = int(rbb.width * w_img)
                h = int(rbb.height * h_img)
                print(f"Face bbox: x={x}, y={y}, w={w}, h={h}")

                # pass bbox ints into your animation function
                create_animated_face(img, (x, y, w, h), audio_path, output_path)

            else:
                print("No face detected, using basic avatar")
                create_basic_avatar(audio_path, output_path, "No face detected")

    except Exception as e:
        print(f"Face generation error: {str(e)}")
        create_basic_avatar(audio_path, output_path,
                            f"Face animation failed: {str(e)}")




def generate_elevenlabs_tts(text, voice_id="21m00Tcm4TlvDq8ikWAM"):  # Rachel voice
    url = f"https://api.elevenlabs.io/v1/text-to-speech/{voice_id}"
    headers = {
        "Accept": "audio/mpeg",
        "Content-Type": "application/json",
        "xi-api-key": "YOUR_API_KEY"  # Get from elevenlabs.io
    }
    data = {
        "text": text,
        "model_id": "eleven_monolingual_v1",
        "voice_settings": {
            "stability": 0.5,
            "similarity_boost": 0.5
        }
    }
    response = requests.post(url, json=data, headers=headers)
    return response.content




def create_default_face():
    """Create a more realistic default face image that SadTalker can detect"""
    # Use a larger, more realistic face
    img = np.ones((512, 512, 3), dtype=np.uint8) * 245

    # Draw a more proportional face
    face_center = (256, 256)
    face_width = 180
    face_height = 220

    # Face outline (oval shape)
    cv2.ellipse(img, face_center, (face_width // 2, face_height // 2), 0, 0, 360, (220, 200, 180), -1)

    # Eyes (larger and more defined)
    left_eye_center = (200, 220)
    right_eye_center = (312, 220)

    # Eye whites
    cv2.ellipse(img, left_eye_center, (25, 15), 0, 0, 360, (255, 255, 255), -1)
    cv2.ellipse(img, right_eye_center, (25, 15), 0, 0, 360, (255, 255, 255), -1)

    # Iris
    cv2.circle(img, left_eye_center, 12, (100, 150, 200), -1)
    cv2.circle(img, right_eye_center, 12, (100, 150, 200), -1)

    # Pupils
    cv2.circle(img, left_eye_center, 6, (20, 20, 20), -1)
    cv2.circle(img, right_eye_center, 6, (20, 20, 20), -1)

    # Eyebrows (more prominent)
    cv2.ellipse(img, (200, 195), (30, 8), 0, 0, 180, (120, 100, 80), -1)
    cv2.ellipse(img, (312, 195), (30, 8), 0, 0, 180, (120, 100, 80), -1)

    # Nose (more defined)
    nose_points = np.array([
        [256, 240],
        [248, 270],
        [256, 280],
        [264, 270]
    ], np.int32)
    cv2.fillPoly(img, [nose_points], (200, 180, 160))

    # Nostrils
    cv2.circle(img, (250, 275), 3, (180, 160, 140), -1)
    cv2.circle(img, (262, 275), 3, (180, 160, 140), -1)

    # Mouth (larger and more defined)
    cv2.ellipse(img, (256, 320), (35, 12), 0, 0, 180, (150, 100, 100), -1)

    # Add some facial contours for better detection
    cv2.ellipse(img, (180, 280), (15, 40), 70, 0, 180, (200, 180, 160), -1)  # Left cheek
    cv2.ellipse(img, (332, 280), (15, 40), 110, 0, 180, (200, 180, 160), -1)  # Right cheek

    return img


def create_animated_face(img, bbox_tuple, audio_path, output_path,
                         codec='h264_high', quality='high', duration=None):
    """Create animated face video from an (x,y,w,h) bounding box tuple."""
    print("Creating animated face video...")
    fps = 30
    if duration is None:
        duration = get_audio_duration(audio_path)  # fallback to audio length
    frames = int(fps * duration)  # ensure int

    height, width = img.shape[:2]

    # Try several codecs
    codecs = ['mp4v', 'MJPG', 'XVID']
    out = None
    final_path = output_path

    for c in codecs:
        try:
            fourcc = cv2.VideoWriter_fourcc(*c)
            test_path = output_path.replace('.mp4', f'_{c}.mp4')
            out = cv2.VideoWriter(test_path, fourcc, fps, (width, height))
            if out.isOpened():
                print(f"Using codec: {c}")
                final_path = test_path
                break
            else:
                if out:
                    out.release()
                out = None
        except Exception as codec_e:
            print(f"Codec {c} failed: {codec_e}")
            continue

    if out is None:
        print("All codecs failed, using basic avatar")
        create_basic_avatar(audio_path, output_path, "Codec failed")
        return

    try:
        # Unpack the tuple we passed in
        x, y, w, h = bbox_tuple
        print(f"Face bbox: x={x}, y={y}, w={w}, h={h}")

        for i in range(frames):
            frame = img.copy()

            # Mouth movement intensity
            mouth_intensity = abs(np.sin(i * 0.3)) * abs(np.sin(i * 0.1))

            # Get face region
            face_region = frame[y:y + h, x:x + w]

            # Mouth animation
            mouth_y_rel = int(h * 0.7)
            mouth_x_rel = int(w * 0.5)
            mouth_w = int(w * 0.2)
            mouth_h = int(5 + mouth_intensity * 20)

            if mouth_y_rel < h and mouth_x_rel < w:
                cv2.ellipse(face_region, (mouth_x_rel, mouth_y_rel),
                            (mouth_w, mouth_h), 0, 0, 180, (120, 80, 80), -1)
                if mouth_h > 10:
                    cv2.ellipse(face_region, (mouth_x_rel, mouth_y_rel - 2),
                                (mouth_w - 5, 3), 0, 0, 180, (240, 240, 240), -1)

            # Eye blinking
            if i % 120 < 8:
                eye_y_rel = int(h * 0.35)
                left_eye_x = int(w * 0.35)
                right_eye_x = int(w * 0.65)
                cv2.ellipse(face_region, (left_eye_x, eye_y_rel),
                            (int(w * 0.08), 4), 0, 0, 180, (200, 180, 160), -1)
                cv2.ellipse(face_region, (right_eye_x, eye_y_rel),
                            (int(w * 0.08), 4), 0, 0,*


def create_basic_avatar(audio_path, video_path, text, codec='h264_high', quality='high'):
    """Fallback basic avatar"""
    print("Creating basic avatar...")
    fps = 30
    duration = get_audio_duration(audio_path)  # use actual audio length
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
            verify_cmd = ['ffprobe', '-v', 'quiet', '-print_format', 'json', '-show_format', '-show_streams',
                          output_path]
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
                    0.3 * abs(np.sin(time_factor * 15)) +  # Secondary articulation
                    0.1 * abs(np.sin(time_factor * 25))  # Fine details
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


def get_reference_face():
    """Get a reference face image - use real face if available"""
    real_face_path = '/app/reference_face.jpg'

    if os.path.exists(real_face_path):
        return real_face_path

    # Create and save a better default face
    default_face = create_default_face()
    cv2.imwrite(real_face_path, default_face)
    return real_face_path


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
            'tts_ready': os.getenv('TTS_API_URL') is not None,
            'sadtalker_installed': os.path.exists('/app/SadTalker'),
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


BENCHMARK_FILE = "/tmp/sadtalker_fps.txt"

def benchmark_sadtalker_fps(source_image, audio_path, fps=25):
    """
    Run a very short SadTalker test (e.g., 2s of audio) and measure how many frames/sec we get.
    Saves the result so we only need to benchmark once.
    """
    if os.path.exists(BENCHMARK_FILE):
        with open(BENCHMARK_FILE, "r") as f:
            return float(f.read().strip())

    print("Benchmarking SadTalker render speed...")

    cmd = [
        "python", "/app/SadTalker/inference.py",
        "--driven_audio", audio_path,
        "--source_image", source_image,
        "--result_dir", "/tmp/sadtalker_bench",
        "--still", "--preprocess", "crop", "--enhancer", "gfpgan"
    ]

    start = time.time()
    subprocess.run(cmd, timeout=30)  # force stop after 30s just in case
    elapsed = time.time() - start

    # count frames written
    frame_dir = "/tmp/sadtalker_bench/frames"
    if not os.path.exists(frame_dir):
        print("⚠️ Benchmark failed, defaulting to 5 fps")
        fps_est = 5.0
    else:
        frame_count = len([f for f in os.listdir(frame_dir) if f.endswith(".png")])
        fps_est = frame_count / elapsed if elapsed > 0 else 5.0

    with open(BENCHMARK_FILE, "w") as f:
        f.write(str(fps_est))

    print(f"✅ Benchmark complete: {fps_est:.2f} fps")
    return fps_est


def calculate_timeout(audio_duration,
                      target_fps=25,
                      safety=1.5,
                      source_image=None,
                      test_audio=None):
    """
    Use benchmarked fps to compute a safe timeout for SadTalker.
    """
    # use the passed-in paths if provided, otherwise defaults
    img_path = source_image or "/app/faces/2.jpg"
    audio_path = test_audio or "/app/test_audio.wav"

    fps_est = benchmark_sadtalker_fps(img_path, audio_path, target_fps)
    render_time = (audio_duration * target_fps) / fps_est
    timeout = int(render_time * safety)
    return timeout



if __name__ == '__main__':
    app.debug = True
    app.run(host='0.0.0.0', port=7860)