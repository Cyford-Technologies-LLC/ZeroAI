# avatar/avatar_endpoints.py - Clean endpoints-only version

import os
import logging
import tempfile
import traceback
import json
import subprocess
from datetime import datetime

# Flask imports
from flask import Flask, request, jsonify, send_file, Response
from flask_cors import CORS

# Core functionality imports (your separated modules)
from audio_processor import call_tts_service_with_options, normalize_audio
from video_processor import convert_video_with_codec, get_mimetype_for_codec
from sadtalker_generator import generate_sadtalker_video
from simple_face_generator import generate_talking_face
from utility import clean_text, check_ffmpeg_available, get_disk_usage, get_memory_info, load_and_preprocess_image

# Import streaming components
try:
    from avatar_stream_api import StreamingAvatarGenerator, init_websocket

    STREAMING_AVAILABLE = True
except ImportError:
    print("Warning: Streaming components not available")
    STREAMING_AVAILABLE = False

# Import options handling
try:
    from avatar_options import (
        sanitize_options,
        validate_streaming_request,
        get_streaming_preset,
        STREAMING_PRESETS,
        VALIDATION_RULES,
        get_endpoint_info
    )
except ImportError as e:
    # Fallback for missing functions
    from avatar_options import sanitize_options


    def validate_streaming_request(data):
        return True, ""


    def get_streaming_preset(name):
        return {}


    STREAMING_PRESETS = {}
    VALIDATION_RULES = {}


    def get_endpoint_info(endpoint):
        return {"endpoint": endpoint, "options": {}}


    print(f"Warning: Some streaming functions not available: {e}")

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
TTS_API_URL = os.getenv('TTS_API_URL', 'http://tts:5000/synthesize')
DEFAULT_CODEC = 'h264_fast'
ref_image_path = '/app/faces/2.jpg'

# Ensure directories exist
os.makedirs("/app/static", exist_ok=True)
os.makedirs("/app/faces", exist_ok=True)


# Global error handler
@app.errorhandler(Exception)
def handle_exception(e):
    logger.error("Unhandled Exception: %s\n%s", e, traceback.format_exc())
    return {"error": str(e), "type": type(e).__name__}, 500


# ============================================================================
# STREAMING ENDPOINTS
# ============================================================================

# Simple modification to your existing /stream endpoint in avatar_endpoints.py


@app.route('/stream', methods=['POST'])
def stream_avatar():
    """Stream talking avatar video in real-time - now with SadTalker support"""
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

        # ADD THESE LINES - get mode and delivery_mode from options
        mode = options.get("mode", "auto")  # 'simple', 'sadtalker', or 'auto'
        delivery_mode = options.get("delivery_mode", "base64")  # 'url' or 'base64'
        logger.info(f"Delivery mode requested: {delivery_mode}")

        logger.info(f"=== AVATAR STREAMING START ===")
        logger.info(f"Mode: {streaming_mode}/{mode}, Prompt: {prompt[:50]}...")
        logger.info(f"Chunk duration: {chunk_duration}s, Frame rate: {frame_rate} fps")
        logger.info(f"Delivery mode: {delivery_mode}")

        # Initialize streaming generator
        streaming_generator = StreamingAvatarGenerator(
            tts_api_url=TTS_API_URL,
            ref_image_path=ref_image_path,
            device="cuda"
        )

        # Validate and preprocess image
        img = load_and_preprocess_image(source_image_input)
        if img is None:
            return jsonify({"error": "Image load failed"}), 500

        # Extract SadTalker options
        sadtalker_options = {
            "timeout": options.get("timeout", 1200),
            "enhancer": options.get("enhancer", None),
            "split_chunks": options.get("split_chunks", False),
            "chunk_length": options.get("chunk_length", 10)
        }

        # Generate stream based on mode - ADD delivery_mode parameter
        if streaming_mode == "chunked":
            stream_generator = streaming_generator.generate_chunked_stream(
                prompt=prompt,
                source_image=img,
                chunk_duration=chunk_duration,
                tts_engine=tts_engine,
                tts_options=tts_options,
                codec=codec,
                quality=quality,
                frame_rate=frame_rate,
                mode=mode,
                delivery_mode=delivery_mode,  # ADD THIS
                **sadtalker_options,
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
                buffer_size=buffer_size,
                mode=mode,
                **sadtalker_options,
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

        # Handle TTS options
        tts_options = {}
        for key in ["voice", "rate", "pitch", "language"]:
            if key in options:
                tts_options[key] = options[key]

        # Get mode from URL args or data
        mode = request.args.get('mode', options.get('mode', 'simple'))
        codec = request.args.get('codec', options.get('codec', DEFAULT_CODEC))
        quality = request.args.get('quality', options.get('quality', 'high'))

        # Extract SadTalker options
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
# DEBUG AND UTILITY ENDPOINTS
# ============================================================================

@app.route('/debug/status')
def debug_status():
    """Debug status endpoint"""
    try:
        return jsonify({
            'timestamp': str(datetime.now()),
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


@app.route('/health')
def health():
    """Health check endpoint"""
    return jsonify({
        'status': 'ok',
        'tts_ready': bool(TTS_API_URL),
        'models': 'TTS + MediaPipe + SadTalker',
        'modes': ['simple', 'sadtalker'],
        'streaming_modes': ['chunked', 'realtime'] if STREAMING_AVAILABLE else [],
        'codecs': ['h264_high', 'h264_medium', 'h264_fast', 'h265_high', 'webm_high', 'webm_fast'],
        'default_codec': DEFAULT_CODEC,
        'websocket_enabled': STREAMING_AVAILABLE,
        'endpoints': [
            '/generate (POST) - Generate complete avatar video',
            '/stream (POST) - HTTP streaming endpoint',
            '/stream/ws - WebSocket streaming',
            '/debug/status - System status',
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


# ============================================================================
# APPLICATION STARTUP
# ============================================================================

if __name__ == '__main__':
    logger.info("Avatar Endpoints API starting...")

    try:
        from gevent import pywsgi
        from geventwebsocket.handler import WebSocketHandler

        app.debug = False
        server = pywsgi.WSGIServer(("0.0.0.0", 7860), app, handler_class=WebSocketHandler)
        logger.info("Server starting on http://0.0.0.0:7860")
        server.serve_forever()
    except ImportError:
        logger.warning("Gevent not available, using standard Flask server")
        app.run(host='0.0.0.0', port=7860, debug=False)