# avatar/avatar_api.py - Complete optimized version

from flask import Flask, request, jsonify, send_file, Response
from flask_cors import CORS
import os, subprocess, tempfile, requests, base64, traceback, unicodedata, shutil, glob, time
from pydub import AudioSegment
import mediapipe as mp
from pathlib import Path
import torch, numpy as np, cv2
from datetime import datetime
import json
import wave
import logging

# Import streaming components
try:
    from avatar_stream_api import StreamingAvatarGenerator, init_websocket

    STREAMING_AVAILABLE = True
except ImportError:
    print("Warning: Streaming components not available")
    STREAMING_AVAILABLE = False

# Import options handling
from avatar_options import (
    sanitize_options,
    validate_streaming_request,
    get_streaming_preset,
    STREAMING_PRESETS,
    VALIDATION_RULES,
    get_endpoint_info
)

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Initialize Flask app
app = Flask(__name__)
CORS(app)

# Initialize WebSocket support if available
if STREAMING_AVAILABLE:
    try:
        init_websocket(app)
        logger.info("WebSocket streaming initialized")
    except Exception as e:
        logger.warning(f"WebSocket initialization failed: {e}")
        STREAMING_AVAILABLE = False

# Configuration
OLLAMA_HOST = os.getenv('OLLAMA_HOST', 'http://ollama:11434')
TTS_API_URL = os.getenv('TTS_API_URL', 'http://tts:5000/synthesize')
device = "cuda" if torch.cuda.is_available() else "cpu"
DEFAULT_CODEC = 'h264_fast'
ref_image_path = '/app/faces/2.jpg'
benchmark_file = "/app/static/benchmark_info.json"

# Ensure directories exist
os.makedirs("/app/static", exist_ok=True)
os.makedirs("/app/faces", exist_ok=True)


# Global error handler
@app.errorhandler(Exception)
def handle_exception(e):
    logger.error("Unhandled Exception: %s\n%s", e, traceback.format_exc())
    return {"error": str(e), "type": type(e).__name__}, 500


# ============================================================================
# UTILITY FUNCTIONS
# ============================================================================

def clean_text(text: str) -> str:
    """Normalize and sanitize input text before TTS"""
    if not text:
        return "Hello"
    text = unicodedata.normalize("NFKC", text)
    text = text.strip()
    text = " ".join(text.split())
    text = "".join(ch for ch in text if ch.isprintable())
    return text


def get_audio_duration(audio_path):
    """Get audio duration in seconds"""
    try:
        with wave.open(audio_path, 'rb') as wf:
            frames = wf.getnframes()
            rate = wf.getframerate()
            duration = frames / float(rate)
        return duration
    except Exception as e:
        logger.error(f"Failed to get audio duration: {e}")
        return 0.0


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
                    if img_input.startswith("data:image"):
                        img_input = img_input.split(",", 1)[1]
                    img_data = base64.b64decode(img_input)
                    img_arr = np.frombuffer(img_data, np.uint8)
                    img = cv2.imdecode(img_arr, cv2.IMREAD_COLOR)
                except Exception:
                    pass

        # Fallback
        if img is None and fallback and os.path.exists(fallback):
            img = cv2.imread(fallback)

        if img is not None:
            # Resize for consistency
            img = cv2.resize(img, (512, 512))
            # Optional: denoise / normalize
            img = cv2.fastNlMeansDenoisingColored(img, None, 10, 10, 7, 21)
        return img
    except Exception as e:
        logger.error(f"Image load error: {e}")
        return cv2.imread(fallback) if os.path.exists(fallback) else None


def call_tts_service_with_options(text, file_path, tts_engine='espeak', tts_options=None):
    """Call TTS service with options"""
    try:
        payload = {'text': text, 'engine': tts_engine}
        if tts_options:
            payload.update(tts_options)

        response = requests.post(TTS_API_URL, json=payload, timeout=60)
        if response.status_code == 200:
            with open(file_path, 'wb') as f:
                f.write(response.content)
            return True
        logger.error(f"TTS error {response.status_code}: {response.text[:200]}")
        return False
    except Exception as e:
        logger.error(f"TTS call failed: {e}")
        return False


def normalize_audio(audio_path):
    """Normalize audio format"""
    fixed_path = audio_path.replace('.wav', '_fixed.wav')
    cmd = ["ffmpeg", "-y", "-i", audio_path, "-ac", "1", "-ar", "16000",
           "-acodec", "pcm_s16le", fixed_path]
    try:
        subprocess.run(cmd, check=True, capture_output=True)
        return fixed_path
    except subprocess.CalledProcessError as e:
        logger.error(f"Audio normalization failed: {e}")
        return audio_path


# ============================================================================
# STREAMING ENDPOINTS
# ============================================================================

@app.route('/stream', methods=['POST'])
def stream_avatar():
    """Stream talking avatar video in real-time"""
    if not STREAMING_AVAILABLE:
        return jsonify({"error": "Streaming not available"}), 503

    try:
        data = request.json or {}

        # Validate the streaming request first
        is_valid, error_msg = validate_streaming_request(data)
        if not is_valid:
            return jsonify({"error": error_msg}), 400

        # Sanitize options
        options = sanitize_options("stream_avatar", data)

        # Extract parameters
        streaming_mode = options["streaming_mode"]
        prompt = clean_text(options["prompt"])
        source_image_input = options["image"]
        tts_engine = options["tts_engine"]
        tts_options = options["tts_options"]
        codec = options["codec"]
        quality = options["quality"]
        chunk_duration = options["chunk_duration"]
        frame_rate = options["frame_rate"]
        buffer_size = options["buffer_size"]

        logger.info(f"=== AVATAR STREAMING START ===")
        logger.info(f"Mode: {streaming_mode}, Prompt: {prompt[:50]}...")
        logger.info(f"Chunk duration: {chunk_duration}s, Frame rate: {frame_rate} fps")

        # Initialize streaming generator
        streaming_generator = StreamingAvatarGenerator(
            tts_api_url=TTS_API_URL,
            ref_image_path=ref_image_path,
            device=device
        )

        # Validate and preprocess image
        img = load_and_preprocess_image(source_image_input)
        if img is None:
            return jsonify({"error": "Image load failed"}), 500

        # Generate stream based on mode
        if streaming_mode == "chunked":
            stream_generator = streaming_generator.generate_chunked_stream(
                prompt=prompt,
                source_image=img,
                chunk_duration=chunk_duration,
                tts_engine=tts_engine,
                tts_options=tts_options,
                codec=codec,
                quality=quality,
                frame_rate=frame_rate
            )
        elif streaming_mode == "realtime":
            stream_generator = streaming_generator.generate_realtime_stream(
                prompt=prompt,
                source_image=img,
                tts_engine=tts_engine,
                tts_options=tts_options,
                codec=codec,
                quality=quality,
                frame_rate=frame_rate,
                buffer_size=buffer_size
            )
        else:
            return jsonify({"error": f"Invalid streaming mode: {streaming_mode}"}), 400

        return Response(
            stream_generator,
            mimetype='multipart/x-mixed-replace; boundary=frame'
        )

    except Exception as e:
        logger.error("Streaming error: %s\n%s", e, traceback.format_exc())
        return jsonify({"error": str(e)}), 500


@app.route('/stream/ws')
def stream_avatar_websocket():
    """WebSocket endpoint for real-time bidirectional avatar streaming"""
    if not STREAMING_AVAILABLE:
        return jsonify({"error": "WebSocket streaming not available"}), 503

    try:
        from avatar_stream_api import handle_websocket_stream
        return handle_websocket_stream()
    except Exception as e:
        logger.error("WebSocket streaming error: %s", e)
        return jsonify({"error": str(e)}), 500


# ============================================================================
# MAIN GENERATION ENDPOINT
# ============================================================================

@app.route('/generate', methods=['POST'])
def generate_avatar():
    """Generate talking avatar video from prompt and image"""
    try:
        data = request.json or {}
        if not data:
            return jsonify({"error": "No JSON data provided"}), 400

        # Sanitize options
        options = sanitize_options("generate_avatar", data)

        # Extract parameters
        prompt = clean_text(options["prompt"])
        source_image_input = options["image"]
        tts_engine = options["tts_engine"]
        # Handle both old and new TTS option formats
        if "tts_options" in options:
            tts_options = options["tts_options"]
        else:
            # Legacy support - convert individual options to dict
            tts_options = {}
            for key in ["voice", "rate", "pitch", "language"]:
                if key in options:
                    tts_options[key] = options[key]

        codec = options["codec"]
        quality = options["quality"]
        mode = options["mode"]

        # Extract SadTalker options if present
        sadtalker_options = {}
        if "sadtalker_options" in options:
            sadtalker_options = options["sadtalker_options"]
        else:
            # Legacy support
            sadtalker_options = {
                "timeout": options.get("timeout", 1200),
                "enhancer": options.get("enhancer", None),
                "split_chunks": options.get("split_chunks", False),
                "chunk_length": options.get("chunk_length", 10)
            }

        logger.info(f"=== AVATAR GENERATION START ===")
        logger.info(f"Mode: {mode}, Codec: {codec}, Quality: {quality}")
        logger.info(f"Prompt: {prompt[:50]}...")
        logger.info(f"TTS Engine: {tts_engine}, Options: {tts_options}")

        # Load and validate image
        img = load_and_preprocess_image(source_image_input)
        if img is None:
            return jsonify({"error": "Image load failed"}), 500

        # Create temporary audio file
        with tempfile.NamedTemporaryFile(suffix=".wav", delete=False) as audio_file:
            audio_path = audio_file.name

        video_path = "/app/static/avatar_video.avi"

        try:
            # Generate TTS
            if not call_tts_service_with_options(prompt, audio_path, tts_engine, tts_options):
                return jsonify({"error": "TTS generation failed"}), 500

            audio_path = normalize_audio(audio_path)

            # Generate video based on mode
            if mode == "sadtalker":
                logger.info("=== ATTEMPTING SADTALKER MODE ===")
                success = generate_sadtalker_video(
                    audio_path,
                    video_path,
                    prompt,
                    codec,
                    quality,
                    **sadtalker_options,
                    source_image=source_image_input
                )

                if not success:
                    logger.info("=== SADTALKER FAILED - FALLBACK TO SIMPLE FACE ===")
                    generate_talking_face(source_image_input, audio_path, video_path, codec, quality)
            else:
                logger.info("=== USING SIMPLE/MEDIAPIPE MODE ===")
                generate_talking_face(source_image_input, audio_path, video_path, codec, quality)

            # Convert video with specified codec
            final_path = convert_video_with_codec(video_path, audio_path, codec, quality)
            if final_path and os.path.exists(final_path):
                return send_file(final_path, mimetype=get_mimetype_for_codec(codec), as_attachment=False)

            return jsonify({"error": "Video creation failed"}), 500

        finally:
            # Cleanup temporary files
            for temp_file in [audio_path, audio_path.replace('.wav', '_fixed.wav')]:
                if os.path.exists(temp_file):
                    try:
                        os.unlink(temp_file)
                    except:
                        pass

    except Exception as e:
        logger.error("Generation error: %s\n%s", e, traceback.format_exc())
        return jsonify({"error": str(e)}), 500


# ============================================================================
# VIDEO GENERATION FUNCTIONS
# ============================================================================

def generate_sadtalker_video(audio_path, video_path, prompt, codec, quality,
                             timeout=1200, enhancer=None, split_chunks=False,
                             chunk_length=10, source_image="/app/faces/2.jpg"):
    """Run SadTalker with optional chunking"""
    if not os.path.exists("/app/SadTalker"):
        logger.warning("SadTalker not found")
        return False

    try:
        result_dir = "/app/static/sadtalker_output"
        os.makedirs(result_dir, exist_ok=True)

        duration = get_audio_duration(audio_path)
        logger.info(f"Audio duration: {duration:.2f}s")

        # Auto-enable chunking for long audio
        if duration > 80 and not split_chunks:
            split_chunks = True
            logger.info("Auto-enabling chunking for long audio")

        if split_chunks:
            chunks = split_audio(audio_path, chunk_length)
            video_parts = []

            for idx, chunk_path in enumerate(chunks):
                logger.info(f"Processing chunk {idx + 1}/{len(chunks)}")
                chunk_result_dir = os.path.join(result_dir, f"chunk_{idx}")
                os.makedirs(chunk_result_dir, exist_ok=True)

                cmd = [
                    "python", "/app/SadTalker/inference.py",
                    "--driven_audio", chunk_path,
                    "--source_image", source_image,
                    "--result_dir", chunk_result_dir,
                    "--still", "--preprocess", "crop"
                ]
                if enhancer:
                    cmd += ["--enhancer", enhancer]

                subprocess.run(cmd, timeout=timeout, check=True)

                # Find generated video
                video_candidates = glob.glob(os.path.join(chunk_result_dir, "**", "*.avi"), recursive=True)
                video_candidates += glob.glob(os.path.join(chunk_result_dir, "**", "*.mp4"), recursive=True)

                if video_candidates:
                    best_file = max(video_candidates, key=os.path.getmtime)
                    video_parts.append(best_file)
                else:
                    logger.error(f"No video found for chunk {idx}")
                    return False

            # Concatenate chunks
            if len(video_parts) > 1:
                concat_videos(video_parts, video_path)
            else:
                shutil.copy(video_parts[0], video_path)

            # Cleanup chunks
            for idx in range(len(chunks)):
                chunk_result_dir = os.path.join(result_dir, f"chunk_{idx}")
                shutil.rmtree(chunk_result_dir, ignore_errors=True)
                chunk_file = f"{audio_path}_chunk{idx}.wav"
                if os.path.exists(chunk_file):
                    os.remove(chunk_file)
        else:
            # Single video generation
            cmd = [
                "python", "/app/SadTalker/inference.py",
                "--driven_audio", audio_path,
                "--source_image", source_image,
                "--result_dir", result_dir,
                "--still", "--preprocess", "crop"
            ]
            if enhancer:
                cmd += ["--enhancer", enhancer]

            subprocess.run(cmd, timeout=timeout, check=True)

            # Find generated video
            video_candidates = glob.glob(os.path.join(result_dir, "**", "*.avi"), recursive=True)
            video_candidates += glob.glob(os.path.join(result_dir, "**", "*.mp4"), recursive=True)

            if video_candidates:
                best_file = max(video_candidates, key=os.path.getmtime)
                shutil.copy(best_file, video_path)
            else:
                return False

        return True

    except Exception as e:
        logger.error(f"SadTalker generation failed: {e}")
        return False


def split_audio(audio_path, chunk_length_s=10):
    """Split audio into chunks"""
    try:
        audio = AudioSegment.from_file(audio_path)
        chunk_length_ms = chunk_length_s * 1000
        chunks = []

        for i in range(0, len(audio), chunk_length_ms):
            chunk = audio[i:i + chunk_length_ms]
            out_path = f"{audio_path}_chunk{i // chunk_length_ms}.wav"
            chunk.export(out_path, format="wav")
            chunks.append(out_path)

        return chunks
    except Exception as e:
        logger.error(f"Audio splitting failed: {e}")
        return [audio_path]


def concat_videos(video_list, output_path):
    """Concatenate video files using ffmpeg"""
    try:
        list_file = "/tmp/concat_list.txt"
        with open(list_file, "w") as f:
            for v in video_list:
                f.write(f"file '{v}'\n")

        cmd = ["ffmpeg", "-y", "-f", "concat", "-safe", "0", "-i", list_file, "-c", "copy", output_path]
        subprocess.run(cmd, check=True, capture_output=True)
        return output_path
    except Exception as e:
        logger.error(f"Video concatenation failed: {e}")
        return None


def generate_talking_face(image_path, audio_path, output_path, codec=None, quality=None):
    """Generate realistic talking face using MediaPipe"""
    try:
        logger.info("Starting face detection...")
        mp_face_detection = mp.solutions.face_detection

        # Load source image
        if os.path.exists(image_path):
            img = cv2.imread(image_path)
            logger.info(f"Loaded image: {image_path}")
        else:
            logger.info("Creating default face image...")
            img = create_default_face()

        with mp_face_detection.FaceDetection(model_selection=0, min_detection_confidence=0.5) as face_detection:
            results = face_detection.process(cv2.cvtColor(img, cv2.COLOR_BGR2RGB))

            if results.detections:
                logger.info(f"Found {len(results.detections)} faces")
                detection = results.detections[0]
                h_img, w_img = img.shape[:2]
                rbb = detection.location_data.relative_bounding_box
                x = int(rbb.xmin * w_img)
                y = int(rbb.ymin * h_img)
                w = int(rbb.width * w_img)
                h = int(rbb.height * h_img)
                logger.info(f"Face bbox: x={x}, y={y}, w={w}, h={h}")

                create_animated_face(img, (x, y, w, h), audio_path, output_path)
            else:
                logger.info("No face detected, using basic avatar")
                create_basic_avatar(audio_path, output_path, "No face detected")

    except Exception as e:
        logger.error(f"Face generation error: {e}")
        create_basic_avatar(audio_path, output_path, f"Face animation failed: {str(e)}")


def create_default_face():
    """Create a realistic default face image"""
    img = np.ones((512, 512, 3), dtype=np.uint8) * 245

    # Draw face
    face_center = (256, 256)
    face_width, face_height = 180, 220

    # Face outline
    cv2.ellipse(img, face_center, (face_width // 2, face_height // 2), 0, 0, 360, (220, 200, 180), -1)

    # Eyes
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

    # Eyebrows
    cv2.ellipse(img, (200, 195), (30, 8), 0, 0, 180, (120, 100, 80), -1)
    cv2.ellipse(img, (312, 195), (30, 8), 0, 0, 180, (120, 100, 80), -1)

    # Nose
    nose_points = np.array([[256, 240], [248, 270], [256, 280], [264, 270]], np.int32)
    cv2.fillPoly(img, [nose_points], (200, 180, 160))

    # Nostrils
    cv2.circle(img, (250, 275), 3, (180, 160, 140), -1)
    cv2.circle(img, (262, 275), 3, (180, 160, 140), -1)

    # Mouth
    cv2.ellipse(img, (256, 320), (35, 12), 0, 0, 180, (150, 100, 100), -1)

    return img


def create_animated_face(img, detection, audio_path, output_path):
    """Create animated face video"""
    logger.info("Creating animated face video...")
    fps = 30
    duration = get_audio_duration(audio_path)
    duration = max(0.1, float(duration))
    frames = int(round(fps * duration))

    height, width = img.shape[:2]

    # Try multiple codecs
    codec_choices = ['mp4v', 'MJPG', 'XVID']
    out = None

    for codec in codec_choices:
        try:
            fourcc = cv2.VideoWriter_fourcc(*codec)
            out = cv2.VideoWriter(output_path, fourcc, fps, (width, height))
            if out.isOpened():
                logger.info(f"Using codec: {codec}")
                break
            else:
                if out:
                    out.release()
                out = None
        except Exception as e:
            logger.error(f"Codec {codec} failed: {e}")
            continue

    if out is None:
        logger.error("All codecs failed, using basic avatar")
        create_basic_avatar(audio_path, output_path, "Codec failed")
        return

    try:
        # Parse detection
        if isinstance(detection, (tuple, list)) and len(detection) == 4:
            x, y, w, h = map(int, detection)
        else:
            bbox = detection.location_data.relative_bounding_box
            x = int(bbox.xmin * width)
            y = int(bbox.ymin * height)
            w = int(bbox.width * width)
            h = int(bbox.height * height)

        # Ensure valid bounds
        x = max(0, int(x))
        y = max(0, int(y))
        w = max(0, int(min(w, width - x)))
        h = max(0, int(min(h, height - y)))

        if w <= 0 or h <= 0:
            create_basic_avatar(audio_path, output_path, "Empty bbox")
            return

        # Render frames
        for i in range(frames):
            frame = img.copy()

            # Mouth animation
            mouth_intensity = abs(np.sin(i * 0.3)) * abs(np.sin(i * 0.1))

            face_region = frame[y:y + h, x:x + w]
            if face_region.size == 0:
                continue

            # Mouth geometry
            mouth_y_rel = int(h * 0.7)
            mouth_x_rel = int(w * 0.5)
            mouth_w = max(1, int(w * 0.2))
            mouth_h = max(1, int(5 + mouth_intensity * 20))

            # Draw mouth
            if mouth_y_rel < h and mouth_x_rel < w:
                cv2.ellipse(face_region, (mouth_x_rel, mouth_y_rel), (mouth_w, mouth_h),
                            0, 0, 180, (120, 80, 80), -1)

                if mouth_h > 10:
                    cv2.ellipse(face_region, (mouth_x_rel, mouth_y_rel - 2),
                                (max(1, mouth_w - 5), 3), 0, 0, 180, (240, 240, 240), -1)

            # Eye blinking
            if i % 120 < 8:
                eye_y_rel = int(h * 0.35)
                left_eye_x = int(w * 0.35)
                right_eye_x = int(w * 0.65)

                cv2.ellipse(face_region, (left_eye_x, eye_y_rel),
                            (max(1, int(w * 0.08)), 4), 0, 0, 180, (200, 180, 160), -1)
                cv2.ellipse(face_region, (right_eye_x, eye_y_rel),
                            (max(1, int(w * 0.08)), 4), 0, 0, 180, (200, 180, 160), -1)

            frame[y:y + h, x:x + w] = face_region
            out.write(frame)

        logger.info("Animated face video completed")

    finally:
        if out:
            out.release()


def create_basic_avatar(audio_path, video_path, text):
    """Fallback basic avatar"""
    logger.info("Creating basic avatar...")
    fps = 30
    duration = get_audio_duration(audio_path)
    frames = int(fps * duration)

    codecs = ['mp4v', 'MJPG', 'XVID']
    out = None

    for codec in codecs:
        try:
            fourcc = cv2.VideoWriter_fourcc(*codec)
            out = cv2.VideoWriter(video_path, fourcc, fps, (640, 480))
            if out.isOpened():
                logger.info(f"Basic avatar using codec: {codec}")
                break
            else:
                if out:
                    out.release()
                out = None
        except Exception as e:
            logger.error(f"Basic codec {codec} failed: {e}")
            continue

    if out is None:
        logger.error("No working codec found!")
        return

    try:
        for i in range(frames):
            frame = np.zeros((480, 640, 3), dtype=np.uint8)
            frame.fill(30)

            # Draw basic animated face
            cv2.circle(frame, (320, 240), 100, (220, 200, 180), -1)  # Face
            cv2.circle(frame, (295, 215), 12, (80, 60, 40), -1)  # Left eye
            cv2.circle(frame, (345, 215), 12, (80, 60, 40), -1)  # Right eye
            cv2.circle(frame, (297, 213), 4, (255, 255, 255), -1)  # Left highlight
            cv2.circle(frame, (347, 213), 4, (255, 255, 255), -1)  # Right highlight

            # Nose
            cv2.ellipse(frame, (320, 245), (8, 12), 0, 0, 180, (200, 180, 160), -1)

            # Animate mouth
            mouth_open = 5 + int(15 * abs(np.sin(i * 0.3)) * abs(np.sin(i * 0.1)))
            cv2.ellipse(frame, (320, 275), (25, mouth_open), 0, 0, 180, (100, 80, 80), -1)

            # Eyebrows
            cv2.ellipse(frame, (295, 195), (15, 5), 0, 0, 180, (120, 100, 80), -1)
            cv2.ellipse(frame, (345, 195), (15, 5), 0, 0, 180, (120, 100, 80), -1)

            cv2.putText(frame, text[:30], (50, 50), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (255, 255, 255), 2)
            out.write(frame)

        logger.info("Basic avatar completed")
    finally:
        if out:
            out.release()


def convert_video_with_codec(video_path, audio_path, codec, quality):
    """Convert video using specified codec"""
    logger.info(f"Converting video with codec: {codec}, quality: {quality}")

    codec_configs = {
        'h264_high': {
            'video_codec': 'libx264', 'audio_codec': 'aac', 'preset': 'ultrafast',
            'crf': '23', 'profile': 'baseline', 'level': '3.0', 'extension': '.mp4'
        },
        'h264_medium': {
            'video_codec': 'libx264', 'audio_codec': 'aac', 'preset': 'medium',
            'crf': '23', 'profile': 'main', 'level': '4.0', 'extension': '.mp4'
        },
        'h264_fast': {
            'video_codec': 'libx264', 'audio_codec': 'aac', 'preset': 'ultrafast',
            'crf': '28', 'profile': 'baseline', 'level': '3.1', 'extension': '.mp4'
        },
        'h265_high': {
            'video_codec': 'libx265', 'audio_codec': 'aac', 'preset': 'slow',
            'crf': '20', 'extension': '.mp4'
        },
        'webm_high': {
            'video_codec': 'libvpx-vp9', 'audio_codec': 'libopus', 'crf': '20',
            'b:v': '2M', 'extension': '.webm'
        },
        'webm_fast': {
            'video_codec': 'libvpx', 'audio_codec': 'libvorbis', 'crf': '30',
            'b:v': '500k', 'b:a': '96k', 'cpu-used': '5', 'deadline': 'realtime',
            'extension': '.webm'
        }
    }

    config = codec_configs.get(codec, codec_configs['h264_fast'])
    output_path = video_path.replace('.avi', config['extension'])

    try:
        cmd = ['ffmpeg', '-i', video_path, '-i', audio_path]

        # Video settings
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

        # Audio settings
        cmd.extend(['-c:a', config['audio_codec']])
        if 'b:a' in config:
            cmd.extend(['-b:a', config['b:a']])

        # WebM specific settings
        if config['video_codec'] == 'libvpx':
            if 'cpu-used' in config:
                cmd.extend(['-cpu-used', config['cpu-used']])
            if 'deadline' in config:
                cmd.extend(['-deadline', config['deadline']])
            cmd.extend(['-auto-alt-ref', '0', '-lag-in-frames', '0'])

        # Universal settings
        cmd.extend(['-pix_fmt', 'yuv420p'])
        if config['extension'] == '.mp4':
            cmd.extend(['-movflags', '+faststart'])
        cmd.extend(['-shortest', '-y', output_path])

        result = subprocess.run(cmd, capture_output=True, text=True)

        if result.returncode == 0 and os.path.exists(output_path):
            logger.info(f"Video conversion successful: {output_path}")
            return output_path
        else:
            logger.error(f"Video conversion failed: {result.stderr}")
            return None

    except Exception as e:
        logger.error(f"Video conversion error: {e}")
        return None


def get_mimetype_for_codec(codec):
    """Get MIME type for codec"""
    mime_types = {
        'h264_high': 'video/mp4', 'h264_medium': 'video/mp4', 'h264_fast': 'video/mp4',
        'h265_high': 'video/mp4', 'webm_high': 'video/webm', 'webm_fast': 'video/webm'
    }
    return mime_types.get(codec, 'video/mp4')


# ============================================================================
# DEBUG AND UTILITY ENDPOINTS
# ============================================================================

@app.route('/debug/streaming')
def debug_streaming():
    """Debug streaming capabilities"""
    try:
        return jsonify({
            'streaming_available': STREAMING_AVAILABLE,
            'supported_modes': ['chunked', 'realtime'] if STREAMING_AVAILABLE else [],
            'websocket_enabled': STREAMING_AVAILABLE,
            'presets': list(STREAMING_PRESETS.keys()) if STREAMING_AVAILABLE else [],
            'validation_rules': VALIDATION_RULES if STREAMING_AVAILABLE else {},
            'streaming_options': get_endpoint_info('stream_avatar') if STREAMING_AVAILABLE else {}
        })
    except Exception as e:
        return jsonify({'error': str(e)})


@app.route('/stream/presets')
def get_streaming_presets_endpoint():
    """Get available streaming presets"""
    if not STREAMING_AVAILABLE:
        return jsonify({"error": "Streaming not available"}), 503

    try:
        return jsonify({
            'presets': STREAMING_PRESETS,
            'default': 'balanced'
        })
    except Exception as e:
        return jsonify({'error': str(e)})


@app.route('/stream/presets/<preset_name>')
def apply_preset_endpoint(preset_name):
    """Apply a streaming preset and return the configuration"""
    if not STREAMING_AVAILABLE:
        return jsonify({"error": "Streaming not available"}), 503

    try:
        preset_config = get_streaming_preset(preset_name)
        return jsonify({
            'preset': preset_name,
            'config': preset_config,
            'usage': 'Include these options in your /stream POST request'
        })
    except Exception as e:
        return jsonify({'error': str(e)})


@app.route('/test/stream/chunked')
def test_chunked_stream():
    """Test chunked streaming with a simple prompt"""
    if not STREAMING_AVAILABLE:
        return jsonify({"error": "Streaming not available"}), 503

    try:
        test_data = {
            "prompt": "This is a test of chunked streaming. Each sentence becomes a chunk.",
            "streaming_mode": "chunked",
            "chunk_duration": 2.0,
            "frame_rate": 20,
            "codec": "h264_fast",
            "quality": "medium"
        }

        is_valid, error_msg = validate_streaming_request(test_data)
        if not is_valid:
            return jsonify({"error": error_msg}), 400

        return jsonify({
            "status": "test_ready",
            "message": "Use POST /stream with this payload",
            "test_payload": test_data,
            "curl_example": f"""curl -X POST {request.host_url}stream \\
  -H "Content-Type: application/json" \\
  -d '{json.dumps(test_data)}'"""
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/test/stream/realtime')
def test_realtime_stream():
    """Test realtime streaming with a simple prompt"""
    if not STREAMING_AVAILABLE:
        return jsonify({"error": "Streaming not available"}), 503

    try:
        test_data = {
            "prompt": "Real-time test. Quick response.",
            "streaming_mode": "realtime",
            "frame_rate": 15,
            "buffer_size": 3,
            "codec": "h264_fast",
            "quality": "fast",
            "low_latency": True
        }

        is_valid, error_msg = validate_streaming_request(test_data)
        if not is_valid:
            return jsonify({"error": error_msg}), 400

        return jsonify({
            "status": "test_ready",
            "message": "Use POST /stream with this payload",
            "test_payload": test_data,
            "curl_example": f"""curl -X POST {request.host_url}stream \\
  -H "Content-Type: application/json" \\
  -d '{json.dumps(test_data)}'"""
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/debug/status')
def debug_status():
    """Debug status endpoint"""
    try:
        return jsonify({
            'timestamp': str(datetime.now()),
            'device': device,
            'tts_ready': bool(TTS_API_URL),
            'sadtalker_installed': os.path.exists('/app/SadTalker'),
            'streaming_available': STREAMING_AVAILABLE,
            'modes': ['simple', 'sadtalker'],
            'streaming_modes': ['chunked', 'realtime'] if STREAMING_AVAILABLE else [],
            'codecs': ['h264_high', 'h264_medium', 'h264_fast', 'h265_high', 'webm_high', 'webm_fast'],
            'quality_levels': ['high', 'medium', 'fast'],
            'default_codec': DEFAULT_CODEC,
            'ffmpeg_available': check_ffmpeg_available(),
            'disk_space': get_disk_usage(),
            'memory_info': get_memory_info()
        })
    except Exception as e:
        return jsonify({'error': str(e)})


@app.route('/debug/logs')
def debug_logs():
    """Debug logs endpoint"""
    try:
        logs = [
            f"Avatar system running on {device}",
            f"TTS service: {'Ready' if TTS_API_URL else 'Not configured'}",
            f"SadTalker: {'Available' if os.path.exists('/app/SadTalker') else 'Not installed'}",
            f"Streaming: {'Available' if STREAMING_AVAILABLE else 'Not available'}",
            f"MediaPipe: Ready"
        ]
        return jsonify({'logs': logs, 'timestamp': str(datetime.now())})
    except Exception as e:
        return jsonify({'error': str(e)})


@app.route('/health')
def health():
    """Health check endpoint"""
    return jsonify({
        'status': 'ok',
        'device': device,
        'tts_ready': bool(TTS_API_URL),
        'models': 'TTS + MediaPipe + SadTalker',
        'modes': ['simple', 'sadtalker'],
        'streaming_modes': ['chunked', 'realtime'] if STREAMING_AVAILABLE else [],
        'codecs': ['h264_high', 'h264_medium', 'h264_fast', 'h265_high', 'webm_high', 'webm_fast'],
        'default_codec': DEFAULT_CODEC,
        'websocket_enabled': STREAMING_AVAILABLE,
        'streaming_presets': list(STREAMING_PRESETS.keys()) if STREAMING_AVAILABLE else [],
        'endpoints': [
            '/generate (POST) - Generate complete avatar video',
            '/stream (POST) - HTTP streaming endpoint' + (' (available)' if STREAMING_AVAILABLE else ' (unavailable)'),
            '/stream/ws - WebSocket streaming' + (' (available)' if STREAMING_AVAILABLE else ' (unavailable)'),
            '/stream/presets - Available streaming presets',
            '/test/stream/chunked - Test chunked streaming',
            '/test/stream/realtime - Test realtime streaming',
            '/debug/status - System status',
            '/debug/logs - System logs',
            '/debug/streaming - Streaming debug info',
            '/health - This endpoint'
        ]
    })


@app.route('/test-mp4')
def test_mp4():
    """Generate a minimal test MP4 to verify pipeline"""
    try:
        test_path = '/app/static/test_video.mp4'
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


# ============================================================================
# UTILITY FUNCTIONS
# ============================================================================

def check_ffmpeg_available():
    """Check if FFmpeg is available"""
    try:
        result = subprocess.run(['ffmpeg', '-version'], capture_output=True, text=True)
        return result.returncode == 0
    except:
        return False


def get_disk_usage():
    """Get disk usage information"""
    try:
        import shutil
        total, used, free = shutil.disk_usage('/app')
        return {
            'total_gb': round(total / (1024 ** 3), 2),
            'used_gb': round(used / (1024 ** 3), 2),
            'free_gb': round(free / (1024 ** 3), 2),
            'usage_percent': round((used / total) * 100, 1)
        }
    except:
        return 'Unknown'


def get_memory_info():
    """Get memory usage information"""
    try:
        import psutil
        memory = psutil.virtual_memory()
        return {
            'total_gb': round(memory.total / (1024 ** 3), 2),
            'available_gb': round(memory.available / (1024 ** 3), 2),
            'usage_percent': memory.percent
        }
    except:
        return 'Unknown'


# ============================================================================
# APPLICATION STARTUP
# ============================================================================

if __name__ == '__main__':
    # Log startup information
    logger.info("=" * 50)
    logger.info("AVATAR API STARTING")
    logger.info("=" * 50)
    logger.info(f"Device: {device}")
    logger.info(f"TTS URL: {TTS_API_URL}")
    logger.info(f"Default codec: {DEFAULT_CODEC}")
    logger.info(f"Reference image: {ref_image_path}")
    logger.info(f"Streaming available: {STREAMING_AVAILABLE}")
    logger.info(f"SadTalker available: {os.path.exists('/app/SadTalker')}")
    logger.info(f"FFmpeg available: {check_ffmpeg_available()}")
    logger.info("=" * 50)

    # Start server
    try:
        from gevent import pywsgi
        from geventwebsocket.handler import WebSocketHandler

        app.debug = False  # Set to True for development
        server = pywsgi.WSGIServer(("0.0.0.0", 7860), app, handler_class=WebSocketHandler)
        logger.info("Server starting on http://0.0.0.0:7860")
        server.serve_forever()
    except ImportError:
        # Fallback to standard Flask server if gevent not available
        logger.warning("Gevent not available, using standard Flask server (WebSocket streaming disabled)")
        app.run(host='0.0.0.0', port=7860, debug=False)