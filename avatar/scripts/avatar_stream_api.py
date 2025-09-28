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
from video_processor import convert_video_with_codec, get_mimetype_for_codec, concat_videos
from simple_face_generator import generate_talking_face
from sadtalker_generator import generate_sadtalker_video

from utility import clean_text, check_ffmpeg_available, get_disk_usage, get_memory_info, load_and_preprocess_image

import logging
import time


logger = logging.getLogger(__name__)

tts_processor = TTSProcessor()


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

    def __init__(self, tts_api_url: str, ref_image_path: str, device: str = "cpu"):
        self.tts_api_url = tts_api_url
        self.ref_image_path = ref_image_path
        self.device = device

        # Streaming state
        self.is_streaming = False
        self.frame_queue = queue.Queue(maxsize=100)
        self.audio_queue = queue.Queue(maxsize=50)

        # Threading
        self.executor = ThreadPoolExecutor(max_workers=4)
        self.cleanup_threads = []

        # Caching for performance
        self.face_cache = {}
        self.audio_cache = {}

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

    def generate_chunked_stream(self, prompt: str, source_image: np.ndarray,
                                chunk_duration: float = 3.0, tts_engine: str = 'espeak',
                                tts_options: Dict = None, codec: str = 'h264_fast',
                                quality: str = 'medium', frame_rate: int = 30,
                                mode: str = 'auto', timeout: int = 300, enhancer: str = None,
                                split_chunks: bool = True, chunk_length: float = 10.0,
                                delivery_mode: str = 'url') -> Generator[bytes, None, None]:
        """
        Generate video chunks and return video URLs or base64 data for progressive playback
        """
        import base64
        import shutil

        logger.info(f"Starting chunked stream - Mode: {mode}, FPS: {frame_rate}, Delivery: {delivery_mode}")

        try:
            self.is_streaming = True

            # Split prompt into sentences for chunking
            sentences = self._split_into_sentences(prompt)

            # Send initial info with immediate flush
            init_info = {
                'status': 'starting',
                'total_chunks': len(sentences),
                'mode': mode,
                'delivery_mode': delivery_mode
            }

            # Send initial frame with explicit flush
            init_json = json.dumps(init_info)
            chunk_data = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(init_json)}\r\n\r\n{init_json}\r\n"
            yield chunk_data.encode()
            yield b''  # Force flush

            for i, sentence in enumerate(sentences):
                if not self.is_streaming:
                    break

                logger.info(f"Processing chunk {i + 1}/{len(sentences)}: {sentence[:30]}...")

                # Generate audio for this chunk
                audio_duration = 0
                audio_path = None

                try:
                    if tts_engine == 'edge':
                        audio_path = _generate_edge_tts_chunk(sentence, i, tts_options or {})
                    else:
                        audio_path = _generate_espeak_chunk(sentence, i, tts_options or {})

                    if audio_path and os.path.exists(audio_path):
                        from pydub import AudioSegment
                        audio = AudioSegment.from_file(audio_path)
                        audio_duration = len(audio) / 1000.0
                        logger.info(f"Audio duration: {audio_duration:.2f}s")

                except Exception as e:
                    logger.error(f"Audio generation failed for chunk {i}: {e}")
                    # Send error frame
                    error_info = {
                        "chunk_id": i,
                        "error": f"Audio generation failed: {str(e)}",
                        "ready": False
                    }
                    error_json = json.dumps(error_info)
                    error_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(error_json)}\r\n\r\n{error_json}\r\n"
                    yield error_frame.encode()
                    yield b''  # Force flush
                    continue

                # Generate video for this chunk
                chunk_filename = f"chunk_{i}_{int(time.time())}.mp4"
                temp_video_path = f"/tmp/{chunk_filename}"

                success = False
                duration = 0

                if audio_path and os.path.exists(audio_path):
                    try:
                        # Save source image temporarily
                        temp_source = f'/tmp/source_{i}.png'
                        cv2.imwrite(temp_source, cv2.cvtColor(source_image, cv2.COLOR_RGB2BGR))

                        # Generate video
                        result = self.generator.test(
                            source_image=temp_source,
                            driven_audio=audio_path,
                            result_dir='/app/static/sadtalker_output'
                        )

                        if result and os.path.exists(result):
                            shutil.move(result, temp_video_path)

                            # Get duration
                            cap = cv2.VideoCapture(temp_video_path)
                            fps = cap.get(cv2.CAP_PROP_FPS) or 25
                            frame_count = cap.get(cv2.CAP_PROP_FRAME_COUNT)
                            duration = frame_count / fps if fps > 0 else audio_duration
                            cap.release()

                            success = True

                        # Cleanup temp files
                        if os.path.exists(temp_source):
                            os.unlink(temp_source)
                        if audio_path and os.path.exists(audio_path):
                            os.unlink(audio_path)

                    except Exception as e:
                        logger.error(f"Video generation error for chunk {i}: {e}")

                # Prepare and send chunk response
                if success and os.path.exists(temp_video_path):
                    chunk_info = {
                        "chunk_id": i,
                        "duration": duration,
                        "ready": True,
                        "sentence": sentence,
                        "mode": mode
                    }

                    if delivery_mode == 'base64':
                        # Read and encode video as base64
                        with open(temp_video_path, 'rb') as video_file:
                            video_data = video_file.read()
                            video_base64 = base64.b64encode(video_data).decode('utf-8')
                        chunk_info["video_data"] = f"data:video/mp4;base64,{video_base64}"

                        # Clean up temp file
                        if os.path.exists(temp_video_path):
                            os.unlink(temp_video_path)
                    else:
                        # Copy to static directory for URL access
                        static_path = f"/app/static/{chunk_filename}"
                        shutil.copy(temp_video_path, static_path)
                        chunk_info["video_url"] = f"/static/{chunk_filename}"

                        # Clean up temp file
                        if os.path.exists(temp_video_path):
                            os.unlink(temp_video_path)

                    # Send chunk with explicit content length and immediate flush
                    chunk_json = json.dumps(chunk_info)
                    chunk_response = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(chunk_json)}\r\n\r\n{chunk_json}\r\n"
                    yield chunk_response.encode()
                    yield b''  # FORCE FLUSH - this ensures immediate delivery

                    logger.info(f"✅ Chunk {i} sent and flushed immediately - Duration: {duration:.2f}s")

                else:
                    # Send failure notification
                    fail_info = {
                        "chunk_id": i,
                        "ready": False,
                        "error": "Failed to generate video"
                    }
                    fail_json = json.dumps(fail_info)
                    fail_response = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(fail_json)}\r\n\r\n{fail_json}\r\n"
                    yield fail_response.encode()
                    yield b''  # Force flush

            # Send completion frame
            complete_info = {
                'status': 'complete',
                'total_chunks': len(sentences),
                'mode': mode
            }
            complete_json = json.dumps(complete_info)
            complete_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(complete_json)}\r\n\r\n{complete_json}\r\n"
            yield complete_frame.encode()
            yield b''  # Final flush

            # Final boundary
            yield b"--frame--\r\n"

            logger.info("✅ Chunked stream completed successfully")

        except Exception as e:
            logger.error(f"Stream generation failed: {e}")
            error_info = {"error": str(e), "status": "failed"}
            error_json = json.dumps(error_info)
            error_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(error_json)}\r\n\r\n{error_json}\r\n"
            yield error_frame.encode()
            yield b"--frame--\r\n"

        finally:
            self.is_streaming = False



    def _generate_audio_realtime(self, prompt: str, tts_engine: str, tts_options: Dict):
        """Generate audio in real-time and put in queue"""
        try:
            sentences = self._split_into_sentences(prompt)

            for sentence in sentences:
                if not self.is_streaming:
                    break

                audio_bytes = tts_processor._call_tts_service(sentence, tts_engine, tts_options)
                if audio_bytes:
                    duration, audio_path = tts_processor._process_audio_chunk(audio_bytes)
                    if audio_path:
                        self.audio_queue.put((duration, audio_path))

        except Exception as e:
            logger.error(f"Audio generation error: {e}")
        finally:
            self.audio_queue.put(None)  # Sentinel

    def _generate_frames_realtime(self, source_image: np.ndarray, frame_rate: int, buffer_size: int,
                                  mode: str = 'auto', sadtalker_options: Dict = None):
        """Generate frames in real-time and put in queue with SadTalker support"""
        try:
            while self.is_streaming:
                try:
                    # Get audio chunk
                    audio_item = self.audio_queue.get(timeout=1.0)
                    if audio_item is None:  # Sentinel
                        break

                    duration, audio_path = audio_item

                    # Generate frames for this audio chunk based on mode
                    if mode == 'sadtalker':
                        try:
                            frame_generator = self._generate_sadtalker_frames_for_streaming(
                                source_image, audio_path, duration, frame_rate, sadtalker_options
                            )
                        except Exception as e:
                            logger.warning(f"SadTalker failed: {e}, falling back to simple")
                            frame_generator = self._generate_face_frames(source_image, duration, frame_rate)
                    elif mode == 'auto':
                        # Try SadTalker first, fallback to simple
                        try:
                            if os.path.exists('/app/SadTalker'):
                                frame_generator = self._generate_sadtalker_frames_for_streaming(
                                    source_image, audio_path, duration, frame_rate, sadtalker_options
                                )
                            else:
                                frame_generator = self._generate_face_frames(source_image, duration, frame_rate)
                        except Exception as e:
                            logger.warning(f"SadTalker failed: {e}, falling back to simple")
                            frame_generator = self._generate_face_frames(source_image, duration, frame_rate)
                    else:  # mode == 'simple' or anything else
                        frame_generator = self._generate_face_frames(source_image, duration, frame_rate)

                    # Stream frames immediately as they're generated
                    frame_count = 0
                    for frame_bytes in frame_generator:
                        if not self.is_streaming:
                            break

                        # Add to queue with backpressure control
                        try:
                            self.frame_queue.put(frame_bytes, timeout=0.1)
                            frame_count += 1
                        except queue.Full:
                            # Drop frame if queue is full (prevents memory issues)
                            logger.debug("Frame queue full, dropping frame")

                    logger.info(f"Streamed {frame_count} frames for {duration:.2f}s audio chunk")

                    # Cleanup audio file
                    if audio_path and os.path.exists(audio_path):
                        os.unlink(audio_path)

                except queue.Empty:
                    # No audio available, wait a bit
                    time.sleep(0.1)
                    continue

        except Exception as e:
            logger.error(f"Frame generation error: {e}")
        finally:
            self.frame_queue.put(None)  # Sentinel

    def _generate_sadtalker_frames_for_streaming(self, source_image: np.ndarray, audio_path: str,
                                                 duration: float, frame_rate: int = 30,
                                                 sadtalker_options: Dict = None) -> Generator[bytes, None, None]:
        """Generate SadTalker frames optimized for streaming"""
        try:
            if sadtalker_options is None:
                sadtalker_options = {}

            # Save the numpy array as a temporary image file for SadTalker
            with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as temp_image:
                temp_image_path = temp_image.name
                cv2.imwrite(temp_image_path, source_image)

            # Create temporary video with SadTalker
            with tempfile.NamedTemporaryFile(suffix=".mp4", delete=False) as temp_video:
                temp_video_path = temp_video.name

            try:
                # Generate SadTalker video using your existing function
                success = generate_sadtalker_video(
                    audio_path,
                    temp_video_path,
                    "",
                    'h264_fast',
                    'medium',
                    timeout=sadtalker_options.get('timeout', 300),
                    enhancer=sadtalker_options.get('enhancer', None),
                    split_chunks=sadtalker_options.get('split_chunks', True),
                    chunk_length=int(sadtalker_options.get('chunk_length', duration)),
                    source_image=temp_image_path
                )

                if not success:
                    raise ValueError("SadTalker generation failed")

                # Extract frames from generated video and stream them
                cap = cv2.VideoCapture(temp_video_path)

                while cap.isOpened():
                    ret, frame = cap.read()
                    if not ret:
                        break

                    # Encode frame for streaming
                    _, buffer = cv2.imencode('.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, 80])
                    yield buffer.tobytes()
                    yield

                cap.release()

            finally:
                # Cleanup temp files
                if os.path.exists(temp_video_path):
                    os.unlink(temp_video_path)
                if os.path.exists(temp_image_path):
                    os.unlink(temp_image_path)

        except Exception as e:
            logger.error(f"SadTalker streaming error: {e}")
            # Fallback to simple face animation
            logger.info("Falling back to simple face animation")
            yield from self._generate_face_frames(source_image, duration, frame_rate)

    def _generate_face_frames(self, source_image: np.ndarray, duration: float,
                              frame_rate: int = 30) -> Generator[bytes, None, None]:
        """Generate animated face frames based on audio duration"""
        total_frames = int(duration * frame_rate)
        height, width = source_image.shape[:2]

        # Detect face for animation
        import mediapipe as mp
        mp_face_detection = mp.solutions.face_detection

        with mp_face_detection.FaceDetection(model_selection=0,
                                             min_detection_confidence=0.5) as face_detection:
            results = face_detection.process(cv2.cvtColor(source_image, cv2.COLOR_BGR2RGB))

            if results.detections:
                detection = results.detections[0]
                bbox = detection.location_data.relative_bounding_box
                x = int(bbox.xmin * width)
                y = int(bbox.ymin * height)
                w = int(bbox.width * width)
                h = int(bbox.height * height)
            else:
                # Default face area if no detection
                x, y, w, h = width // 4, height // 4, width // 2, height // 2

        for frame_idx in range(total_frames):
            frame = source_image.copy()

            # Animate mouth based on time
            time_factor = frame_idx / frame_rate
            mouth_intensity = (
                    0.6 * abs(np.sin(time_factor * 8)) +
                    0.3 * abs(np.sin(time_factor * 15)) +
                    0.1 * abs(np.sin(time_factor * 25))
            )

            # Draw animated mouth in face region
            face_region = frame[y:y + h, x:x + w]
            if face_region.size > 0:
                mouth_y_rel = int(h * 0.7)
                mouth_x_rel = int(w * 0.5)
                mouth_w = max(1, int(w * 0.15))
                mouth_h = max(1, int(5 + mouth_intensity * 15))

                if mouth_y_rel < h and mouth_x_rel < w:
                    cv2.ellipse(face_region, (mouth_x_rel, mouth_y_rel),
                                (mouth_w, mouth_h), 0, 0, 180, (120, 80, 80), -1)

                frame[y:y + h, x:x + w] = face_region

            # Encode frame as JPEG for streaming
            _, buffer = cv2.imencode('.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, 80])
            yield buffer.tobytes()

    def _split_into_sentences(self, text: str) -> list:
        """Split text into sentences for chunked processing"""
        import re

        # Simple sentence splitting
        sentences = re.split(r'[.!?]+', text.strip())
        sentences = [s.strip() for s in sentences if s.strip()]

        # Ensure minimum length and split very long sentences
        processed = []
        for sentence in sentences:
            if len(sentence) > 200:  # Split long sentences
                words = sentence.split()
                for i in range(0, len(words), 20):
                    chunk = ' '.join(words[i:i + 20])
                    if chunk:
                        processed.append(chunk)
            else:
                processed.append(sentence)

        return processed or [text]  # Fallback to original text


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