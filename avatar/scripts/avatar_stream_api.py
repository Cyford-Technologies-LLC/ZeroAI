# avatar/avatar_stream_api.py

import os
import time
import json
import queue
import threading
import tempfile
import subprocess
from typing import Generator, Dict, Optional, Tuple
from concurrent.futures import ThreadPoolExecutor

import cv2
import numpy as np
import requests
from flask import request, jsonify
from flask_socketio import SocketIO, emit
import wave




# Core functionality imports (your separated modules)
from audio_processor import call_tts_service_with_options, normalize_audio, TTSProcessor, _generate_edge_tts_chunk , _generate_espeak_chunk
from video_processor import convert_video_with_codec, get_mimetype_for_codec, concat_videos , encode_video_for_delivery
from simple_face_generator import generate_talking_face
from sadtalker_generator import generate_sadtalker_video

from utility import clean_text, check_ffmpeg_available, get_disk_usage, get_memory_info, load_and_preprocess_image

import logging
import time


logger = logging.getLogger(__name__)

tts_processor = TTSProcessor()

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





class StreamingAvatarGenerator(TTSProcessor):
    """
    Handles real-time and chunked avatar video streaming.

    Key Features:
    - Chunked: Pre-generate video segments and stream them
    - Realtime: Generate frames on-the-fly as fast as possible
    - WebSocket support for bidirectional communication
    - Dynamic prompt updates
    - Efficient resource management
    - SadTalker integration with fallback to simple face animation
    """

    def __init__(self, tts_api_url: str, ref_image_path: str = None, device: str = 'cuda' , data: Dict = None):
        """Initialize streaming avatar generator"""
        self.tts_api_url = tts_api_url
        self.ref_image_path = ref_image_path or '/app/faces/2.jpg'
        self.device = device
        self.is_streaming = False



        # Initialize SadTalker generator
        try:
            import sys
            sys.path.append('/app/SadTalker')
            from inference import SadTalker

            # Initialize SadTalker with proper checkpoint paths
            checkpoint_dir = '/app/SadTalker/checkpoints'
            config_dir = '/app/SadTalker/src/config'

            self.generator = SadTalker(
                checkpoint_dir=checkpoint_dir,
                config_dir=config_dir,
                lazy_load=True
            )
            logger.info("✅ SadTalker generator initialized")

        except Exception as e:
            logger.warning(f"SadTalker initialization failed: {e}")
            self.generator = None

        logger.info(f"StreamingAvatarGenerator initialized - Device: {device}")




    def cleanup(self):
        """Clean up resources and stop all threads"""
        self.is_streaming = False

        # Clear queues
        while not self.frame_queue.empty():
            try:
                self.frame_queue.get_nowait()
            except queue.Empty:
                break

        while not self.audio_queue.empty():
            try:
                self.audio_queue.get_nowait()
            except queue.Empty:
                break

        # Wait for threads to finish
        for thread in self.cleanup_threads:
            if thread.is_alive():
                thread.join(timeout=2.0)

        # Shutdown executor
        self.executor.shutdown(wait=False)

        logger.info("StreamingAvatarGenerator cleanup completed")

    def generate_chunked_video_stream(options: dict, prompt: str, source_image,
                                      tts_engine: str, tts_options: dict) -> Generator[bytes, None, None]:
        """
        Generate chunked video stream with full options support
        """
        import json
        import base64
        from audio_processor import _generate_edge_tts_chunk, _generate_espeak_chunk

        logger = logging.getLogger(__name__)

        # Extract ALL streaming options from sanitized options
        streaming_mode = options["streaming_mode"]
        chunk_duration = options["chunk_duration"]  # 3.0 seconds per chunk
        max_duration = options["max_duration"]  # 300 seconds total
        frame_rate = options["frame_rate"]  # 25 fps
        codec = options["codec"]  # h264_fast
        quality = options["quality"]  # medium
        delivery_mode = options["delivery_mode"]  # url or base64

        # Video generation mode
        mode = options.get("mode", "simple")  # simple, sadtalker
        preprocess = options["preprocess"]  # crop, none, resize
        resolution = options["resolution"]  # 256
        still = options["still"]  # True/False
        enhancer = options["enhancer"]  # None or gfpgan
        timeout = options["timeout"]  # 1200 seconds

        # Advanced streaming options
        adaptive_quality = options["adaptive_quality"]
        low_latency = options["low_latency"]
        max_concurrent = options["max_concurrent"]

        logger.info(f"Starting chunked video stream:")
        logger.info(f"  Mode: {mode}, Codec: {codec}, Quality: {quality}")
        logger.info(f"  Chunk duration: {chunk_duration}s, Frame rate: {frame_rate} fps")
        logger.info(f"  Delivery: {delivery_mode}, Resolution: {resolution}")
        logger.info(f"  TTS Engine: {tts_engine}, Options: {tts_options}")

        try:
            # Split text into chunks - support both duration and sentence splitting
            chunks = _create_text_chunks(prompt, chunk_duration, options)

            # Send initial info
            init_info = {
                'status': 'starting',
                'total_chunks': len(chunks),
                'mode': mode,
                'delivery_mode': delivery_mode,
                'chunk_duration': chunk_duration,
                'frame_rate': frame_rate,
                'codec': codec,
                'quality': quality
            }

            init_json = json.dumps(init_info)
            chunk_data = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(init_json)}\r\n\r\n{init_json}\r\n"
            yield chunk_data.encode()
            yield b''  # Force flush

            for i, chunk_text in enumerate(chunks):
                logger.info(f"Processing chunk {i + 1}/{len(chunks)}: {chunk_text[:30]}...")

                # Generate audio with FULL TTS options support
                audio_path = _generate_audio_with_options(chunk_text, i, tts_engine, tts_options)

                if not audio_path:
                    # Send error frame
                    error_info = {"chunk_id": i, "error": "Audio generation failed", "ready": False}
                    error_json = json.dumps(error_info)
                    error_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(error_json)}\r\n\r\n{error_json}\r\n"
                    yield error_frame.encode()
                    yield b''
                    continue

                # Generate video with FULL video options support
                chunk_filename = f"chunk_{i}_{int(time.time())}.mp4"
                temp_video_path = f"/tmp/{chunk_filename}"

                video_success = _generate_video_with_options(
                    chunk_text, i, source_image, audio_path, temp_video_path, options
                )

                if video_success:
                    # Get video duration
                    duration = _get_video_duration(temp_video_path)

                    chunk_info = {
                        "chunk_id": i,
                        "duration": duration,
                        "ready": True,
                        "sentence": chunk_text,
                        "mode": mode,
                        "codec": codec,
                        "quality": quality,
                        "frame_rate": frame_rate
                    }

                    # Handle delivery mode (base64 or URL)
                    delivery_info = encode_video_for_delivery(temp_video_path, delivery_mode, chunk_filename)
                    chunk_info.update(delivery_info)

                    # Send chunk with flush
                    chunk_json = json.dumps(chunk_info)
                    chunk_response = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(chunk_json)}\r\n\r\n{chunk_json}\r\n"
                    yield chunk_response.encode()
                    yield b''  # Force flush

                    logger.info(f"✅ Chunk {i} sent - Duration: {duration:.2f}s, Mode: {mode}")
                else:
                    # Send failure
                    fail_info = {"chunk_id": i, "ready": False, "error": "Video generation failed"}
                    fail_json = json.dumps(fail_info)
                    fail_response = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(fail_json)}\r\n\r\n{fail_json}\r\n"
                    yield fail_response.encode()
                    yield b''

                # Cleanup audio
                if audio_path and os.path.exists(audio_path):
                    os.unlink(audio_path)

            # Send completion
            complete_info = {
                'status': 'complete',
                'total_chunks': len(chunks),
                'mode': mode,
                'total_duration': sum(_get_chunk_duration(chunk) for chunk in chunks)
            }
            complete_json = json.dumps(complete_info)
            complete_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(complete_json)}\r\n\r\n{complete_json}\r\n"
            yield complete_frame.encode()
            yield b''
            yield b"--frame--\r\n"

            logger.info("✅ Chunked video stream completed")

        except Exception as e:
            logger.error(f"Chunked video stream failed: {e}")
            error_info = {"error": str(e), "status": "failed"}
            error_json = json.dumps(error_info)
            error_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(error_json)}\r\n\r\n{error_json}\r\n"
            yield error_frame.encode()
            yield b"--frame--\r\n"

    def _create_text_chunks(prompt: str, chunk_duration: float, options: dict) -> List[str]:
        """Create text chunks based on duration and sentence splitting options"""
        split_chunks = options.get("split_chunks", False)
        chunk_length = options.get("chunk_length", 10)

        if split_chunks:
            # Split by sentences
            import re
            sentences = re.split(r'(?<=[.!?])\s+', prompt)
            sentences = [s.strip() for s in sentences if s.strip()]

            # Group sentences into chunks based on word count
            chunks = []
            current_chunk = []
            current_word_count = 0

            for sentence in sentences:
                words = len(sentence.split())
                if current_word_count + words > chunk_length and current_chunk:
                    chunks.append(' '.join(current_chunk))
                    current_chunk = [sentence]
                    current_word_count = words
                else:
                    current_chunk.append(sentence)
                    current_word_count += words

            if current_chunk:
                chunks.append(' '.join(current_chunk))

            return chunks
        else:
            # Split by estimated duration (rough calculation)
            words_per_second = 2.5  # Average speaking rate
            words_per_chunk = int(chunk_duration * words_per_second)

            words = prompt.split()
            chunks = []

            for i in range(0, len(words), words_per_chunk):
                chunk_words = words[i:i + words_per_chunk]
                chunks.append(' '.join(chunk_words))

            return chunks


# WebSocket Support
socketio = None


def init_websocket(app):
    """Initialize WebSocket support"""
    global socketio
    socketio = SocketIO(app, cors_allowed_origins="*")

    @socketio.on('connect')
    def handle_connect():
        logger.info(f"WebSocket client connected: {request.sid}")
        emit('status', {'message': 'Connected to avatar streaming service'})

    @socketio.on('disconnect')
    def handle_disconnect():
        logger.info(f"WebSocket client disconnected: {request.sid}")

    @socketio.on('start_stream')
    def handle_start_stream(data):
        """Start avatar streaming via WebSocket"""
        try:
            logger.info(f"Starting WebSocket stream for {request.sid}")

            # Extract parameters
            prompt = data.get('prompt', 'Hello world')
            streaming_mode = data.get('mode', 'realtime')  # streaming mode
            generation_mode = data.get('generation_mode', 'auto')  # generation mode
            image_data = data.get('image')  # base64 or URL

            # Initialize generator
            generator = StreamingAvatarGenerator(
                tts_api_url=os.getenv('TTS_API_URL', 'http://tts:5000/synthesize'),
                ref_image_path='/app/faces/2.jpg'
            )

            # Process image
            img = load_and_preprocess_image(image_data)
            if img is None:
                emit('error', {'message': 'Failed to load image'})
                return

            # Start streaming in background thread
            def stream_worker():
                try:
                    if streaming_mode == 'chunked':
                        stream_gen = generator.generate_chunked_stream(
                            prompt, img, mode=generation_mode, **data
                        )
                    else:
                        stream_gen = generator.generate_realtime_stream(
                            prompt, img, mode=generation_mode, **data
                        )

                    for chunk in stream_gen:
                        if chunk:
                            # Parse chunk and emit appropriate event
                            if b'Content-Type: application/json' in chunk:
                                # Extract JSON data
                                json_start = chunk.find(b'\r\n\r\n') + 4
                                json_data = chunk[json_start:chunk.rfind(b'\r\n')]
                                try:
                                    parsed_data = json.loads(json_data.decode())
                                    socketio.emit('stream_info', parsed_data, room=request.sid)
                                except:
                                    pass
                            elif b'Content-Type: image/jpeg' in chunk:
                                # Extract image data
                                img_start = chunk.find(b'\r\n\r\n') + 4
                                img_data = chunk[img_start:chunk.rfind(b'\r\n')]
                                socketio.emit('frame', {'data': img_data.hex()}, room=request.sid)

                except Exception as e:
                    logger.error(f"WebSocket streaming error: {e}")
                    socketio.emit('error', {'message': str(e)}, room=request.sid)
                finally:
                    generator.cleanup()
                    socketio.emit('stream_complete', {}, room=request.sid)

            # Start in background
            thread = threading.Thread(target=stream_worker)
            thread.daemon = True
            thread.start()

            emit('stream_started', {
                'streaming_mode': streaming_mode,
                'generation_mode': generation_mode,
                'prompt': prompt[:50]
            })

        except Exception as e:
            logger.error(f"WebSocket start stream error: {e}")
            emit('error', {'message': str(e)})

    @socketio.on('stop_stream')
    def handle_stop_stream():
        """Stop current streaming"""
        logger.info(f"Stopping stream for {request.sid}")
        emit('stream_stopped', {})


def handle_websocket_stream():
    """Handle WebSocket stream endpoint (called from main API)"""
    if socketio is None:
        return jsonify({"error": "WebSocket not initialized"}), 500

    return jsonify({"message": "Use WebSocket connection on /stream/ws endpoint"})


def generate_realtime_stream(self, options: Dict, prompt: str, source_image: np.ndarray,
                             tts_engine: str = 'espeak', tts_options: Dict = None,
                             codec: str = 'h264_fast', quality: str = 'medium',
                             frame_rate: int = 30, buffer_size: int = 5, mode: str = 'auto',
                             timeout: int = 300) -> Generator[bytes, None, None]:
    """
    Generate real-time avatar stream with buffering
    """
    logger.info(f"Starting realtime stream - Mode: {mode}, FPS: {frame_rate}, Buffer: {buffer_size}")

    try:
        self.is_streaming = True

        # For realtime, we process the entire text at once but stream frames
        logger.info(f"Processing realtime prompt: {prompt[:50]}...")

        # Generate audio for entire prompt
        audio_duration = 0
        audio_path = None

        try:
            if tts_engine == 'edge':
                audio_path = _generate_edge_tts_chunk(prompt, 0, tts_options or {})
            else:
                audio_path = _generate_espeak_chunk(prompt, 0, tts_options or {})

            if audio_path and os.path.exists(audio_path):
                from pydub import AudioSegment
                audio = AudioSegment.from_file(audio_path)
                audio_duration = len(audio) / 1000.0
                logger.info(f"Total audio duration: {audio_duration:.2f}s")

        except Exception as e:
            logger.error(f"Audio generation failed: {e}")
            return

        # Generate complete video
        video_filename = f"realtime_{int(time.time())}.mp4"
        temp_video_path = f"/tmp/{video_filename}"

        success = False

        if audio_path and os.path.exists(audio_path):
            try:
                # Save source image temporarily
                temp_source = f'/tmp/source_realtime.png'
                cv2.imwrite(temp_source, cv2.cvtColor(source_image, cv2.COLOR_RGB2BGR))

                # Use SadTalker subprocess (same as working /generate)
                import subprocess
                cmd = [
                    'python', '/app/SadTalker/inference.py',
                    '--driven_audio', audio_path,
                    '--source_image', temp_source,
                    '--result_dir', f'/app/static/sadtalker_output/realtime_{int(time.time())}',
                    '--still',
                    '--preprocess', options.get('preprocess', 'crop')
                ]

                if options.get('use_enhancer'):
                    cmd.extend(['--enhancer', 'gfpgan'])

                logger.info(f"Generating realtime video...")
                result = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout)

                if result.returncode == 0:
                    # Find generated video
                    import glob
                    pattern = f'/app/static/sadtalker_output/realtime_*/**/*.mp4'
                    videos = glob.glob(pattern, recursive=True)

                    if videos:
                        latest_video = max(videos, key=os.path.getmtime)
                        shutil.copy(latest_video, temp_video_path)
                        success = True
                        logger.info(f"✅ Realtime video generated")

                # Cleanup
                if os.path.exists(temp_source):
                    os.unlink(temp_source)
                if audio_path and os.path.exists(audio_path):
                    os.unlink(audio_path)

            except Exception as e:
                logger.error(f"Realtime video generation error: {e}")

        # Stream the complete video in chunks
        if success and os.path.exists(temp_video_path):
            # Send initial info
            init_info = {
                'status': 'streaming',
                'mode': 'realtime',
                'total_duration': audio_duration,
                'buffer_size': buffer_size
            }

            init_json = json.dumps(init_info)
            chunk_data = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(init_json)}\r\n\r\n{init_json}\r\n"
            yield chunk_data.encode()
            yield b''  # Force flush

            # Read and stream video data in chunks
            with open(temp_video_path, 'rb') as video_file:
                chunk_size = 64 * 1024  # 64KB chunks
                chunk_id = 0

                while True:
                    video_chunk = video_file.read(chunk_size)
                    if not video_chunk:
                        break

                    # Encode video chunk as base64
                    import base64
                    video_base64 = base64.b64encode(video_chunk).decode('utf-8')

                    chunk_info = {
                        "chunk_id": chunk_id,
                        "data": video_base64,
                        "size": len(video_chunk),
                        "mode": "realtime",
                        "final": False
                    }

                    chunk_json = json.dumps(chunk_info)
                    chunk_response = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(chunk_json)}\r\n\r\n{chunk_json}\r\n"
                    yield chunk_response.encode()
                    yield b''  # Force flush

                    chunk_id += 1

                    # Optional: Add small delay for realtime feel
                    import time
                    time.sleep(0.1)

            # Send completion frame
            complete_info = {
                'status': 'complete',
                'total_chunks': chunk_id,
                'mode': 'realtime'
            }
            complete_json = json.dumps(complete_info)
            complete_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(complete_json)}\r\n\r\n{complete_json}\r\n"
            yield complete_frame.encode()
            yield b''  # Final flush

            # Cleanup
            if os.path.exists(temp_video_path):
                os.unlink(temp_video_path)
        else:
            # Send error
            error_info = {"error": "Realtime video generation failed", "status": "failed"}
            error_json = json.dumps(error_info)
            error_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(error_json)}\r\n\r\n{error_json}\r\n"
            yield error_frame.encode()

        # Final boundary
        yield b"--frame--\r\n"

        logger.info("✅ Realtime stream completed")

    except Exception as e:
        logger.error(f"Realtime stream generation failed: {e}")
        error_info = {"error": str(e), "status": "failed"}
        error_json = json.dumps(error_info)
        error_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(error_json)}\r\n\r\n{error_json}\r\n"
        yield error_frame.encode()
        yield b"--frame--\r\n"

    finally:
        self.is_streaming = False