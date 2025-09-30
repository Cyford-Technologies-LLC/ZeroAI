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


# from a2f.audio2face_options import Audio2FaceOptions, prepare_audio2face_request
from a2f.enhanced_audio2face_integration import (
    EnhancedAudio2FaceGenerator,
    generate_enhanced_audio2face_avatar
)


# # Import streaming components
try:
    from avatar_stream_api import StreamingAvatarGenerator

    print("🔍 StreamingAvatarGenerator Import Details:")
    print(f"Module Path: {StreamingAvatarGenerator.__module__}")
    print(f"Module File: {StreamingAvatarGenerator.__module__}")

    STREAMING_AVAILABLE = True
except ImportError:
    print("Warning: Streaming components not available")
    STREAMING_AVAILABLE = False

from audio2face_integration import (
    generate_audio2face_avatar,
    check_audio2face_requirements,
    Audio2FaceGenerator
)

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





# Force model downloads to use /tmp
os.environ['TORCH_HOME'] = '/tmp/torch_cache'
os.environ['FACEXLIB_CACHE_DIR'] = '/tmp/facexlib_cache'
os.environ['GFPGAN_CACHE_DIR'] = '/tmp/gfpgan_cache'
os.environ['HF_HOME'] = '/tmp/huggingface_cache'

# Create directories if they don't exist
cache_dirs = ['/tmp/torch_cache', '/tmp/facexlib_cache', '/tmp/gfpgan_cache', '/tmp/huggingface_cache']
for cache_dir in cache_dirs:
    try:
        os.makedirs(cache_dir, exist_ok=True)
        os.chmod(cache_dir, 0o777)
    except Exception as e:
        print(f"Warning: Could not create {cache_dir}: {e}")










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
    from avatar_stream_api import StreamingAvatarGenerator
    # if not STREAMING_AVAILABLE:
    #     return jsonify({"error": "Streaming not available"}), 503

    try:
        data = request.json or {}

        # Validate the streaming request first
        is_valid, error_msg = validate_streaming_request(data)
        if not is_valid:
            return jsonify({"error": error_msg}), 400


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


        # Extract parameters
        streaming_mode = request.args.get('streaming_mode', options.get('streaming_mode', 'chunked'))
        # prompt = clean_text(options["prompt"])
        # source_image_input = options["image"]
        # tts_engine = options["tts_engine"]
        # tts_options =request.args.get('streaming_mode', options.get('streaming_mode', 'chunked'))
        codec = request.args.get('codec', options.get('codec', 'mpeg'))
        quality = request.args.get('quality', options.get('quality', 'good'))
        chunk_duration = request.args.get('tts_options', options.get('tts_options', 1))
        frame_rate = request.args.get('frame_rate', options.get('frame_rate', 15))
        buffer_size = request.args.get('buffer_size', options.get('buffer_size', 5))

        # ADD THESE LINES - get mode and delivery_mode from options
        # mode = options.get("mode", "auto")  # 'simple', 'sadtalker', or 'auto'
        # mode = data.get("mode", options.get("mode", "auto"))
        logger.info(f"Original mode from payload: {data.get('mode')}")
        logger.info(f"Mode after processing: {mode}")

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
                options=options,
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
                options=options,
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

        # CHANGE ONLY THIS PART - ADD direct_passthrough and headers
        response = Response(
            stream_generator,
            mimetype='multipart/x-mixed-replace; boundary=frame',
            direct_passthrough=True  # ADD THIS LINE
        )

        # ADD THESE HEADERS
        response.headers['X-Accel-Buffering'] = 'no'
        response.headers['Cache-Control'] = 'no-cache'
        response.headers['Connection'] = 'keep-alive'  # Add this
        response.headers['Transfer-Encoding'] = 'chunked'  # Add this

        return response

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


# Update your existing health endpoint to include Audio2Face status
@app.route('/health')
def health():
    """Health check endpoint - UPDATED to include Audio2Face"""
    try:
        # Check Audio2Face status
        # a2f_status = check_audio2face_requirements()

        return jsonify({
            'status': 'ok',
            'tts_ready': bool(TTS_API_URL),
            'models': 'TTS + MediaPipe + SadTalker + Audio2Face',
            'modes': ['simple', 'sadtalker', 'audio2face'],  # Added audio2face
            'streaming_modes': ['chunked', 'realtime'] if STREAMING_AVAILABLE else [],
            'codecs': ['h264_high', 'h264_medium', 'h264_fast', 'h265_high', 'webm_high', 'webm_fast'],
            'default_codec': DEFAULT_CODEC,
            'websocket_enabled': STREAMING_AVAILABLE,
            # 'audio2face': {
            #     'available': a2f_status['requirements_met'],
            #     'server_reachable': a2f_status['server_reachable'],
            #     'characters_count': len(a2f_status['characters_available'])
            # },
             'endpoints': [
                '/generate (POST) - Generate complete avatar video',
                '/generate/audio2face (POST) - Generate with Audio2Face',  # NEW
                '/stream (POST) - HTTP streaming endpoint',
                '/stream/ws - WebSocket streaming',
                '/audio2face/status - Audio2Face status check',  # NEW
                '/audio2face/characters - List A2F characters',  # NEW
                '/audio2face/test - Test A2F generation',  # NEW
                '/debug/status - System status',
                # '/health - This endpoint'
            ]
        })

    except Exception as e:
        return jsonify({'error': str(e)}), 500


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

# if __name__ == '__main__':
#     logger.info("Avatar Endpoints API starting...")
#
#     try:
#         from gevent import pywsgi
#         from geventwebsocket.handler import WebSocketHandler
#
#         app.debug = False
#         server = pywsgi.WSGIServer(("0.0.0.0", 7860), app, handler_class=WebSocketHandler)
#         logger.info("Server starting on http://0.0.0.0:7860")
#         server.serve_forever()
#     except ImportError:
#         logger.warning("Gevent not available, using standard Flask server")
#         app.run(host='0.0.0.0', port=7860, debug=False)

# Add this to your avatar_endpoints.py file



# ============================================================================
# AUDIO2FACE ENDPOINTS - Add these to your existing avatar_endpoints.py
# ============================================================================

@app.route('/audio2face/generate', methods=['POST'])
def generate_audio2face_avatar_endpoint():
    """
    Generate talking avatar video using NVIDIA Audio2Face

    Required:
    - prompt: Text to speak

    Optional:
    - character_path: A2F character to use (will use default if not specified)
    - tts_engine: TTS engine ('espeak', 'edge', etc.) - defaults to 'espeak'
    - tts_options: TTS options (voice, rate, pitch, language)
    - a2f_server_url: Audio2Face server URL (defaults to localhost:8011)
    """
    try:
        data = request.json or {}
        if not data:
            return jsonify({"error": "No JSON data provided"}), 400

        # Extract parameters
        prompt = clean_text(data.get("prompt", ""))
        if not prompt:
            return jsonify({"error": "Prompt is required"}), 400

        character_path = data.get("character_path")
        tts_engine = data.get("tts_engine", "espeak")
        a2f_server_url = data.get("a2f_server_url", "http://localhost:8011")

        # Handle TTS options
        tts_options = {}
        for key in ["voice", "rate", "pitch", "language"]:
            if key in data:
                tts_options[key] = data[key]

        logger.info(f"=== AUDIO2FACE GENERATION START ===")
        logger.info(f"Prompt: {prompt[:50]}...")
        logger.info(f"Character: {character_path}")
        logger.info(f"TTS Engine: {tts_engine}, Options: {tts_options}")
        logger.info(f"A2F Server: {a2f_server_url}")

        # Generate output path
        timestamp = int(time.time())
        output_filename = f"audio2face_avatar_{timestamp}.mp4"
        output_path = f"/app/static/{output_filename}"

        # Generate avatar using Audio2Face
        success = generate_audio2face_avatar(
            prompt=prompt,
            source_image="",  # A2F uses its own character models
            output_path=output_path,
            tts_engine=tts_engine,
            tts_options=tts_options,
            character_path=character_path,
            a2f_server_url=a2f_server_url
        )

        if success and os.path.exists(output_path):
            logger.info(f"✅ Audio2Face generation completed: {output_filename}")
            return send_file(output_path, mimetype='video/mp4', as_attachment=False)
        else:
            return jsonify({
                "error": "Audio2Face generation failed",
                "suggestion": "Check Audio2Face server status and character availability"
            }), 500

    except Exception as e:
        logger.error("Audio2Face generation error: %s\n%s", e, traceback.format_exc())
        return jsonify({"error": str(e)}), 500


@app.route('/audio2face/status')
def audio2face_status():
    """Check Audio2Face integration status and requirements"""
    try:
        status = check_audio2face_requirements()

        return jsonify({
            'timestamp': str(datetime.now()),
            'audio2face_integration': status,
            'server_url': 'http://localhost:8011',
            'usage': {
                'endpoint': '/audio2face/generate',
                'method': 'POST',
                'required_params': ['prompt'],
                'optional_params': ['character_path', 'tts_engine', 'tts_options', 'a2f_server_url']
            },
            'setup_instructions': [
                "1. Install NVIDIA Audio2Face from Omniverse",
                "2. Start Audio2Face with headless mode enabled",
                "3. Load a character model in Audio2Face",
                "4. Verify server is running on localhost:8011"
            ]
        })

    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/audio2face/characters')
def list_audio2face_characters():
    """List available characters in Audio2Face"""
    try:
        a2f_server_url = request.args.get('server_url', 'http://localhost:8011')

        a2f = Audio2FaceGenerator(a2f_server_url)

        if not a2f.is_connected:
            return jsonify({
                "error": "Audio2Face server not accessible",
                "server_url": a2f_server_url,
                "suggestion": "Make sure Audio2Face is running with headless mode enabled"
            }), 503

        characters = a2f.list_available_characters()

        return jsonify({
            'server_url': a2f_server_url,
            'characters': characters,
            'count': len(characters),
            'current_character': a2f.current_character
        })

    except Exception as e:
        logger.error(f"Error listing Audio2Face characters: {e}")
        return jsonify({'error': str(e)}), 500


@app.route('/audio2face/test', methods=['POST'])
def test_audio2face_generation():
    """Test Audio2Face generation with a simple prompt"""
    try:
        data = request.json or {}
        test_prompt = data.get("prompt", "Hello, this is a test of Audio2Face integration.")
        character_path = data.get("character_path")
        a2f_server_url = data.get("a2f_server_url", "http://localhost:8011")

        logger.info(f"Testing Audio2Face with prompt: {test_prompt[:30]}...")

        # Check if Audio2Face is available
        status = check_audio2face_requirements()
        if not status['requirements_met']:
            return jsonify({
                "error": "Audio2Face requirements not met",
                "status": status,
                "issues": status['issues']
            }), 503

        # Generate test video
        timestamp = int(time.time())
        output_filename = f"audio2face_test_{timestamp}.mp4"
        output_path = f"/app/static/{output_filename}"

        success = generate_audio2face_avatar(
            prompt=test_prompt,
            source_image="",
            output_path=output_path,
            tts_engine="espeak",
            tts_options={},
            character_path=character_path,
            a2f_server_url=a2f_server_url
        )

        if success and os.path.exists(output_path):
            file_size = os.path.getsize(output_path)
            return jsonify({
                "success": True,
                "message": "Audio2Face test completed successfully",
                "video_url": f"/static/{output_filename}",
                "file_size": file_size,
                "character_used": character_path,
                "prompt": test_prompt
            })
        else:
            return jsonify({
                "success": False,
                "error": "Test generation failed",
                "suggestion": "Check Audio2Face server logs for details"
            }), 500

    except Exception as e:
        logger.error(f"Audio2Face test error: {e}")
        return jsonify({"error": str(e)}), 500



@app.route('/audio2face/generate', methods=['POST'])
def generate_audio2face_avatar_complete():
    """
    Generate talking avatar with complete Audio2Face options

    Request body can include:
    - prompt (required): Text to speak
    - tts_engine: TTS engine to use
    - tts_options: TTS configuration
    - preset: Use a preset configuration ('high_quality', 'fast_preview', 'streaming', 'emotional')
    - a2f_options: Complete nested options structure
    - force_mock: Force mock mode for testing

    Or use flat structure for individual options:
    - animation: Animation settings
    - emotion: Emotion parameters
    - audio: Audio processing options
    - character: Character settings
    - post_processing: Post-processing options
    - performance: Performance settings
    - output: Output configuration
    - advanced: Advanced features
    """
    try:
        data = request.json or {}
        if not data:
            return jsonify({"error": "No JSON data provided"}), 400

        # Validate prompt
        prompt = clean_text(data.get("prompt", ""))
        if not prompt:
            return jsonify({"error": "Prompt is required"}), 400

        # Prepare complete request
        prepared_request = prepare_audio2face_request(data)

        # Check for validation errors
        if prepared_request.get("validation_errors"):
            logger.warning(f"Option validation warnings: {prepared_request['validation_errors']}")

        # Extract parameters
        tts_engine = prepared_request["tts_engine"]
        tts_options = prepared_request["tts_options"]
        a2f_options = prepared_request["a2f_options"]
        force_mock = prepared_request["force_mock"]
        a2f_server_url = prepared_request["a2f_server_url"]
        preset = data.get("preset")

        # Log the request
        logger.info(f"=== AUDIO2FACE GENERATION REQUEST ===")
        logger.info(f"Prompt: {prompt[:50]}...")
        logger.info(f"Preset: {preset}")
        logger.info(f"Force Mock: {force_mock}")
        logger.info(f"Options: {json.dumps(a2f_options, indent=2)}")

        # Generate output path
        timestamp = int(time.time())
        request_id = f"{timestamp}_{hash(prompt) & 0xFFFF:04x}"
        output_filename = f"audio2face_{request_id}.mp4"
        output_path = f"/app/static/{output_filename}"

        # Determine mode (real vs mock)
        use_mock = force_mock
        mode_info = {"type": "unknown", "reason": ""}

        if not use_mock:
            # Check if real A2F is available
            try:
                a2f = EnhancedAudio2FaceGenerator(a2f_server_url)
                if a2f.is_connected:
                    use_mock = False
                    mode_info = {"type": "real_enhanced", "reason": "Enhanced A2F server available"}
                else:
                    use_mock = True
                    mode_info = {"type": "mock", "reason": "A2F server not reachable"}
            except:
                use_mock = True
                mode_info = {"type": "mock", "reason": "A2F check failed"}
        else:
            mode_info = {"type": "mock", "reason": "Force mock enabled"}

        logger.info(f"Mode: {mode_info['type']} - {mode_info['reason']}")

        # Generate based on mode
        if use_mock:
            # Use mock for testing
            from mock_audio2face import generate_mock_audio2face_avatar

            character = a2f_options.get("character", {}).get("character_preset", "MockCharacter_Female_01")
            success = generate_mock_audio2face_avatar(
                prompt=prompt,
                source_image="",
                output_path=output_path,
                tts_engine=tts_engine,
                tts_options=tts_options,
                character_path=character
            )
            metadata = {"mode": "mock", "character": character}
        else:
            # Use enhanced real A2F
            success, metadata = generate_enhanced_audio2face_avatar(
                prompt=prompt,
                output_path=output_path,
                tts_engine=tts_engine,
                tts_options=tts_options,
                a2f_options=a2f_options,
                a2f_server_url=a2f_server_url,
                preset=preset
            )

        if success and os.path.exists(output_path):
            logger.info(f"✅ Generation completed: {output_filename}")

            # Prepare response with metadata
            response = send_file(output_path, mimetype='video/mp4', as_attachment=False)
            response.headers['X-Audio2Face-Mode'] = mode_info['type']
            response.headers['X-Audio2Face-Reason'] = mode_info['reason']
            response.headers['X-Request-ID'] = request_id

            # Add metadata as JSON header
            response.headers['X-Metadata'] = json.dumps(metadata)

            return response
        else:
            return jsonify({
                "error": "Audio2Face generation failed",
                "mode": mode_info,
                "metadata": metadata,
                "request_id": request_id
            }), 500

    except Exception as e:
        logger.error(f"Audio2Face endpoint error: {e}\n{traceback.format_exc()}")
        return jsonify({"error": str(e), "traceback": traceback.format_exc()}), 500


@app.route('/audio2face/options', methods=['GET'])
def get_audio2face_options():
    """Get documentation of all available Audio2Face options"""
    try:
        return jsonify({
            "options": Audio2FaceOptions.get_options_documentation(),
            "presets": {
                "high_quality": "Maximum quality with all features enabled",
                "fast_preview": "Quick generation for previews",
                "streaming": "Optimized for real-time streaming",
                "emotional": "Enhanced emotional expressions"
            },
            "categories": {
                "animation": "Core animation and lip-sync settings",
                "emotion": "Emotional expression parameters",
                "audio": "Audio processing and analysis",
                "character": "Character model and rendering",
                "post_processing": "Post-processing filters",
                "performance": "Performance optimization",
                "output": "Output format and quality",
                "advanced": "Advanced features and physics"
            }
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/audio2face/validate', methods=['POST'])
def validate_audio2face_options():
    """Validate Audio2Face options before generation"""
    try:
        data = request.json or {}

        # Prepare and validate
        prepared = prepare_audio2face_request(data)
        is_valid, errors, sanitized = Audio2FaceOptions.validate_options(
            prepared.get("a2f_options", {})
        )

        return jsonify({
            "valid": is_valid,
            "errors": errors,
            "sanitized_options": sanitized,
            "warnings": prepared.get("validation_errors")
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/audio2face/batch', methods=['POST'])
def batch_audio2face_generation():
    """
    Batch generate multiple Audio2Face avatars

    Request body:
    - prompts: List of prompt objects with text and optional option overrides
    - base_options: Base A2F options for all generations
    - preset: Optional preset to use as base
    """
    try:
        data = request.json or {}

        prompts = data.get("prompts", [])
        if not prompts:
            return jsonify({"error": "No prompts provided"}), 400

        # Prepare base options
        base_data = {
            "a2f_options": data.get("base_options", {}),
            "preset": data.get("preset"),
            "tts_engine": data.get("tts_engine", "espeak"),
            "tts_options": data.get("tts_options", {})
        }

        prepared = prepare_audio2face_request(base_data)
        base_options = prepared["a2f_options"]

        # Initialize generator
        a2f_server_url = data.get("a2f_server_url", "http://localhost:8011")
        a2f = EnhancedAudio2FaceGenerator(a2f_server_url)

        if not a2f.is_connected:
            return jsonify({
                "error": "Audio2Face server not connected",
                "suggestion": "Check server status or use force_mock for testing"
            }), 503

        # Generate batch
        batch_id = f"batch_{int(time.time())}"
        results = []

        for i, prompt_data in enumerate(prompts):
            try:
                if isinstance(prompt_data, str):
                    prompt_text = prompt_data
                    prompt_options = {}
                else:
                    prompt_text = prompt_data.get("text", "")
                    prompt_options = prompt_data.get("options", {})

                if not prompt_text:
                    results.append({"index": i, "error": "Empty prompt"})
                    continue

                # Merge options
                merged_options = base_options.copy()
                for key, value in prompt_options.items():
                    if key in merged_options and isinstance(merged_options[key], dict):
                        merged_options[key].update(value)
                    else:
                        merged_options[key] = value

                # Generate
                output_path = f"/app/static/{batch_id}_{i}.mp4"
                success, metadata = generate_enhanced_audio2face_avatar(
                    prompt=clean_text(prompt_text),
                    output_path=output_path,
                    tts_engine=prepared["tts_engine"],
                    tts_options=prepared["tts_options"],
                    a2f_options=merged_options,
                    a2f_server_url=a2f_server_url
                )

                if success:
                    results.append({
                        "index": i,
                        "success": True,
                        "output": f"/static/{batch_id}_{i}.mp4",
                        "metadata": metadata
                    })
                else:
                    results.append({
                        "index": i,
                        "success": False,
                        "error": metadata.get("error", "Generation failed")
                    })

            except Exception as e:
                results.append({
                    "index": i,
                    "error": str(e)
                })

        # Calculate statistics
        successful = sum(1 for r in results if r.get("success"))

        return jsonify({
            "batch_id": batch_id,
            "total": len(prompts),
            "successful": successful,
            "failed": len(prompts) - successful,
            "results": results
        })

    except Exception as e:
        logger.error(f"Batch generation error: {e}")
        return jsonify({"error": str(e)}), 500


@app.route('/audio2face/presets', methods=['GET'])
def list_audio2face_presets():
    """Get detailed information about available presets"""
    try:
        presets = {}

        for preset_name in ["high_quality", "fast_preview", "streaming", "emotional"]:
            preset_options = Audio2FaceOptions.create_preset(preset_name)
            presets[preset_name] = {
                "options": preset_options,
                "description": {
                    "high_quality": "Maximum quality with subdivision, wrinkles, and post-processing",
                    "fast_preview": "Quick generation without expensive effects",
                    "streaming": "Optimized for real-time with lower latency",
                    "emotional": "Enhanced emotional expressions and dynamics"
                }.get(preset_name, "")
            }

        return jsonify(presets)

    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/audio2face/capabilities', methods=['GET'])
def get_audio2face_capabilities():
    """Check server capabilities and available features"""
    try:
        a2f_server_url = request.args.get('server_url', 'http://localhost:8011')

        # Check real A2F
        try:
            a2f = EnhancedAudio2FaceGenerator(a2f_server_url)
            real_capabilities = {
                "available": a2f.is_connected,
                "server_url": a2f_server_url,
                "capabilities": a2f.server_capabilities
            }
        except:
            real_capabilities = {"available": False}

        # Check mock
        mock_available = False
        try:
            from mock_audio2face import MockAudio2FaceGenerator
            mock_available = True
        except:
            pass

        return jsonify({
            "real_audio2face": real_capabilities,
            "mock_audio2face": {"available": mock_available},
            "all_options": Audio2FaceOptions.get_options_documentation(),
            "endpoints": {
                "/audio2face/generate": "Main generation endpoint with all options",
                "/audio2face/options": "Get all available options documentation",
                "/audio2face/validate": "Validate options before generation",
                "/audio2face/batch": "Batch generate multiple avatars",
                "/audio2face/presets": "List available presets",
                "/audio2face/capabilities": "This endpoint"
            }
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500


if __name__ == '__main__':
    logger.info("Avatar Endpoints API starting...")

    # Try to use gevent for better streaming support
    try:
        from gevent import pywsgi
        from geventwebsocket.handler import WebSocketHandler

        logger.info("Starting with gevent WSGI server for optimal streaming...")


        class StreamingWSGIServer(pywsgi.WSGIServer):
            """Custom WSGI server with streaming support"""

            def __init__(self, *args, **kwargs):
                kwargs['log'] = logger
                super().__init__(*args, **kwargs)


        app.debug = False
        server = StreamingWSGIServer(
            ("0.0.0.0", 7860),
            app,
            log=logger
        )

        logger.info("✅ Server starting on http://0.0.0.0:7860 with streaming support")
        server.serve_forever()

    except ImportError:
        logger.warning("Gevent not available, trying alternative streaming methods...")

        # Try waitress as second option
        try:
            from waitress import serve

            logger.info("Starting with Waitress server...")
            serve(
                app,
                host='0.0.0.0',
                port=7860,
                threads=4,
                channel_timeout=120,
                cleanup_interval=10,
                connection_limit=100,
                asyncore_use_poll=True,
                map=None,
                ident='Avatar-API-Server',
                backlog=128,
                recv_bytes=8192,
                send_bytes=1048576,  # 1MB send buffer for video streaming
                outbuf_overflow=1048576 * 10,  # 10MB overflow for large chunks
                outbuf_high_watermark=1048576 * 2,  # 2MB high watermark
            )

        except ImportError:
            logger.warning("Waitress not available, using Flask development server")
            logger.warning("⚠️ For production streaming, install gevent: pip install gevent gevent-websocket")

            # Fallback to Flask development server with threading
            app.run(
                host='0.0.0.0',
                port=7860,
                debug=False,
                threaded=True,
                use_reloader=False,
                use_debugger=False
            )