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
from audio_processor import call_tts_service_with_options, normalize_audio, TTSProcessor
from video_processor import convert_video_with_codec, get_mimetype_for_codec
from sadtalker_generator import generate_sadtalker_video
from simple_face_generator import generate_talking_face
from utility import clean_text, check_ffmpeg_available, get_disk_usage, get_memory_info, load_and_preprocess_image

import logging

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
                # Generate SadTalker video using your existing function - exact same call as in full video
                success = generate_sadtalker_video(
                    audio_path,
                    temp_video_path,
                    "",  # prompt
                    'h264_fast',  # codec
                    'medium',  # quality
                    timeout=sadtalker_options.get('timeout', 300),
                    enhancer=sadtalker_options.get('enhancer', None),
                    split_chunks=sadtalker_options.get('split_chunks', True),
                    chunk_length=int(sadtalker_options.get('chunk_length', duration)),
                    # Convert to int to fix the error
                    source_image=temp_image_path  # Use the temp image file path, not numpy array
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

    def generate_chunked_stream(self, prompt: str, source_image: np.ndarray,
                                chunk_duration: float = 3.0, tts_engine: str = 'espeak',
                                tts_options: Dict = None, codec: str = 'h264_fast',
                                quality: str = 'medium', frame_rate: int = 30,
                                mode: str = 'auto', timeout: int = 300, enhancer: str = None,
                                split_chunks: bool = True, chunk_length: float = 10.0) -> Generator[bytes, None, None]:
        """
        Generate video stream in pre-processed chunks.
        Each chunk is fully rendered before streaming begins.
        Now supports SadTalker with all your existing options.
        """
        logger.info(f"Starting chunked stream - Duration: {chunk_duration}s, FPS: {frame_rate}, Mode: {mode}")

        # Prepare SadTalker options from your existing parameters
        sadtalker_options = {
            'timeout': timeout,
            'enhancer': enhancer,
            'split_chunks': split_chunks,
            'chunk_length': chunk_length
        }

        try:
            self.is_streaming = True

            # Split prompt into sentences for chunking
            sentences = self._split_into_sentences(prompt)

            for i, sentence in enumerate(sentences):
                if not self.is_streaming:
                    break

                logger.info(f"Processing chunk {i + 1}/{len(sentences)}: {sentence[:30]}...")

                # Generate TTS for this chunk
                audio_bytes = tts_processor._call_tts_service(sentence, tts_engine, tts_options)
                if not audio_bytes:
                    continue

                # Process audio
                duration, audio_path = tts_processor._process_audio_chunk(audio_bytes)
                if not audio_path:
                    continue

                try:
                    # Stream chunk header with audio data
                    chunk_info = {
                        'chunk_id': i,
                        'duration': duration,
                        'sentence': sentence,
                        'total_chunks': len(sentences),
                        'mode': mode,
                        'audio_path': audio_path  # Include audio path for client to fetch separately
                    }

                    yield f"--frame\r\nContent-Type: application/json\r\n\r\n{json.dumps(chunk_info)}\r\n".encode()

                    # Also stream the audio data as base64
                    try:
                        with open(audio_path, 'rb') as audio_file:
                            audio_data = audio_file.read()
                            import base64
                            audio_b64 = base64.b64encode(audio_data).decode()
                            audio_info = {
                                'type': 'audio',
                                'chunk_id': i,
                                'data': audio_b64,
                                'format': 'wav'
                            }
                            yield f"--frame\r\nContent-Type: application/json\r\n\r\n{json.dumps(audio_info)}\r\n".encode()
                    except Exception as audio_err:
                        logger.warning(f"Failed to stream audio for chunk {i}: {audio_err}")

                    # Generate video frames based on mode
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

                    # Stream frames
                    for frame_bytes in frame_generator:
                        if not self.is_streaming:
                            break

                        yield (b'--frame\r\n'
                               b'Content-Type: image/jpeg\r\n\r\n' + frame_bytes + b'\r\n')

                        # Control frame rate
                        time.sleep(1.0 / frame_rate)

                finally:
                    # Cleanup audio file
                    if audio_path and os.path.exists(audio_path):
                        os.unlink(audio_path)

            # Stream completion marker
            completion_info = {'status': 'completed', 'total_chunks': len(sentences), 'mode': mode}
            yield f"--frame\r\nContent-Type: application/json\r\n\r\n{json.dumps(completion_info)}\r\n".encode()

        except Exception as e:
            logger.error(f"Chunked streaming error: {e}")
            error_info = {'status': 'error', 'message': str(e), 'mode': mode}
            yield f"--frame\r\nContent-Type: application/json\r\n\r\n{json.dumps(error_info)}\r\n".encode()

        finally:
            self.is_streaming = False
            logger.info("Chunked stream completed")

    def generate_realtime_stream(self, prompt: str, source_image: np.ndarray,
                                 tts_engine: str = 'espeak', tts_options: Dict = None,
                                 codec: str = 'h264_fast', quality: str = 'medium',
                                 frame_rate: int = 30, buffer_size: int = 5,
                                 mode: str = 'auto', timeout: int = 300, enhancer: str = None,
                                 split_chunks: bool = True, chunk_length: float = 10.0) -> Generator[bytes, None, None]:
        """
        Generate video stream in real-time.
        Frames are generated and sent as fast as possible.
        Now supports SadTalker with all your existing options.
        """
        logger.info(f"Starting realtime stream - FPS: {frame_rate}, Buffer: {buffer_size}, Mode: {mode}")

        # Prepare SadTalker options from your existing parameters
        sadtalker_options = {
            'timeout': timeout,
            'enhancer': enhancer,
            'split_chunks': split_chunks,
            'chunk_length': chunk_length
        }

        try:
            self.is_streaming = True

            # Start audio generation in background
            audio_future = self.executor.submit(self._generate_audio_realtime,
                                                prompt, tts_engine, tts_options)

            # Start frame generation with enhanced mode support
            frame_future = self.executor.submit(self._generate_frames_realtime,
                                                source_image, frame_rate, buffer_size, mode, sadtalker_options)

            # Stream initialization
            init_info = {'status': 'streaming', 'mode': f'realtime/{mode}', 'fps': frame_rate}
            yield f"--frame\r\nContent-Type: application/json\r\n\r\n{json.dumps(init_info)}\r\n".encode()

            frame_count = 0
            start_time = time.time()

            while self.is_streaming:
                try:
                    # Get next frame from queue (with timeout)
                    frame_bytes = self.frame_queue.get(timeout=1.0)

                    if frame_bytes is None:  # Sentinel value to stop
                        break

                    # Stream frame
                    yield (b'--frame\r\n'
                           b'Content-Type: image/jpeg\r\n\r\n' + frame_bytes + b'\r\n')

                    frame_count += 1

                    # Adaptive frame rate control
                    elapsed = time.time() - start_time
                    expected_frames = elapsed * frame_rate

                    if frame_count > expected_frames + buffer_size:
                        time.sleep(0.01)  # Slight delay to prevent overwhelming

                except queue.Empty:
                    # No frames available, send keepalive
                    keepalive = {'status': 'buffering', 'frame_count': frame_count, 'mode': mode}
                    yield f"--frame\r\nContent-Type: application/json\r\n\r\n{json.dumps(keepalive)}\r\n".encode()
                    continue

            # Wait for background tasks to complete
            try:
                audio_future.result(timeout=5.0)
                frame_future.result(timeout=5.0)
            except Exception as e:
                logger.warning(f"Background task completion warning: {e}")

            # Stream completion
            completion_info = {'status': 'completed', 'total_frames': frame_count, 'mode': mode}
            yield f"--frame\r\nContent-Type: application/json\r\n\r\n{json.dumps(completion_info)}\r\n".encode()

        except Exception as e:
            logger.error(f"Realtime streaming error: {e}")
            error_info = {'status': 'error', 'message': str(e), 'mode': mode}
            yield f"--frame\r\nContent-Type: application/json\r\n\r\n{json.dumps(error_info)}\r\n".encode()

        finally:
            self.is_streaming = False
            logger.info("Realtime stream completed")

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
            total_duration = 0

            while self.is_streaming:
                try:
                    # Get audio chunk
                    audio_item = self.audio_queue.get(timeout=1.0)
                    if audio_item is None:  # Sentinel
                        break

                    duration, audio_path = audio_item
                    total_duration += duration

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

                    for frame_bytes in frame_generator:
                        if not self.is_streaming:
                            break

                        # Add to queue with backpressure control
                        try:
                            self.frame_queue.put(frame_bytes, timeout=0.1)
                        except queue.Full:
                            # Drop frame if queue is full (prevents memory issues)
                            logger.debug("Frame queue full, dropping frame")

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
                                    data = json.loads(json_data.decode())
                                    socketio.emit('stream_info', data, room=request.sid)
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
        # This would need to be connected to the specific generator instance
        emit('stream_stopped', {})


def handle_websocket_stream():
    """Handle WebSocket stream endpoint (called from main API)"""
    if socketio is None:
        return jsonify({"error": "WebSocket not initialized"}), 500

    return jsonify({"message": "Use WebSocket connection on /stream/ws endpoint"})