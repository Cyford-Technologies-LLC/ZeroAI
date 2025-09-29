# avatar/scripts/avatar_stream_api.py

import os
import time
import json
import shutil
from typing import Generator, Dict, List
import cv2
import numpy as np
import logging

logger = logging.getLogger(__name__)

# Import your existing processors
from audio_processor import _generate_edge_tts_chunk, _generate_espeak_chunk, split_into_sentences , split_by_duration
from video_processor import encode_video_for_delivery, convert_video_with_codec, get_mimetype_for_codec



class StreamingAvatarGenerator:
    """Streaming avatar generator - LOGIC AND ROUTING ONLY"""

    def __init__(self, tts_api_url: str, ref_image_path: str = None, device: str = 'cuda'):
        """Initialize streaming avatar generator"""
        self.tts_api_url = tts_api_url
        self.ref_image_path = ref_image_path or '/app/faces/2.jpg'
        self.device = device
        self.is_streaming = False
        logger.info(f"StreamingAvatarGenerator initialized - Device: {device}")

    def generate_chunked_stream(self, options: Dict, prompt: str, source_image: np.ndarray,
                                chunk_duration: float = 3.0, tts_engine: str = 'espeak',
                                tts_options: Dict = None, codec: str = 'h264_fast',
                                quality: str = 'medium', frame_rate: int = 30,
                                mode: str = 'auto', timeout: int = 300, enhancer: str = None,
                                split_chunks: bool = True, chunk_length: float = 10.0,
                                delivery_mode: str = 'base64') -> Generator[bytes, None, None]:
        """Generate chunked stream using existing processors"""

        logger.info(f"Starting chunked stream - Mode: {mode}, Delivery: {delivery_mode}")
        logger.info(f"Options: TTS={tts_engine}, Codec={codec}, Quality={quality}")

        try:
            self.is_streaming = True

            split_chunks = options.get("split_chunks", False)
            chunk_length = options.get("chunk_length", 10)

            if split_chunks:
                # Split by sentences
                from audio_processor import _split_into_sentences
                chunks = split_into_sentences(prompt)
            else:
                # Split by duration/word count
                chunks = split_by_duration(prompt, chunk_duration)


            # Send initial info
            init_info = {
                'status': 'starting',
                'total_chunks': len(chunks  ),
                    'chunking_method': 'sentences' if split_chunks else 'duration',
                'chunk_duration': chunk_duration,
                'mode': mode,
                'delivery_mode': delivery_mode,
                'tts_engine': tts_engine,
                'codec': codec,
                'quality': quality,
                'frame_rate': frame_rate
            }

            init_json = json.dumps(init_info)
            chunk_data = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(init_json)}\r\n\r\n{init_json}\r\n"
            yield chunk_data.encode()
            yield b''  # Force flush

            for i, chunk_text in enumerate(chunks):
                if not self.is_streaming:
                    break

                logger.info(f"Processing chunk {i + 1}/{len(chunk_text)}: {chunk_text[:30]}...")

                # AUDIO: Use existing audio processor functions
                from audio_processor import generate_audio_for_streaming
                audio_path, audio_duration = generate_audio_for_streaming(
                    chunk_text, i, tts_engine, tts_options or {}
                )

                if not audio_path:
                    # Send error frame
                    error_info = {"chunk_id": i, "error": "Audio generation failed", "ready": False}
                    error_json = json.dumps(error_info)
                    error_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(error_json)}\r\n\r\n{error_json}\r\n"
                    yield error_frame.encode()
                    yield b''
                    continue

                # VIDEO: Use existing video processor functions
                chunk_filename = f"chunk_{i}_{int(time.time())}.mp4"
                temp_video_path = f"/tmp/{chunk_filename}"

                from video_processor import generate_video_for_streaming
                video_success, duration = generate_video_for_streaming(chunk_text, i, source_image, audio_path,
                                                                       temp_video_path, options, audio_duration)

                if video_success:
                    chunk_info = {
                        "chunk_id": i,
                        "duration": duration,
                        "ready": True,
                        "sentence": chunk_text,
                        "mode": mode,
                        "tts_engine": tts_engine,
                        "codec": codec
                    }

                    # Use existing video processor for delivery
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

                # Cleanup audio file
                if audio_path and os.path.exists(audio_path):
                    os.unlink(audio_path)

            # Send completion
            complete_info = {
                'status': 'complete',
                'total_chunks': len(chunk_texts),
                'mode': mode,
                'delivery_mode': delivery_mode
            }
            complete_json = json.dumps(complete_info)
            complete_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(complete_json)}\r\n\r\n{complete_json}\r\n"
            yield complete_frame.encode()
            yield b''
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

    def generate_realtime_stream(self, options: Dict, prompt: str, source_image: np.ndarray,
                                 tts_engine: str = 'espeak', tts_options: Dict = None,
                                 codec: str = 'h264_fast', quality: str = 'medium',
                                 frame_rate: int = 30, buffer_size: int = 5, mode: str = 'auto',
                                 timeout: int = 300) -> Generator[bytes, None, None]:
        """Generate realtime stream using existing processors"""
        import base64

        logger.info(f"Starting realtime stream - Mode: {mode}, FPS: {frame_rate}")

        try:
            self.is_streaming = True

            # Generate audio for entire prompt using existing processors
            from audio_processor import generate_audio_for_streaming
            audio_path, audio_duration = generate_audio_for_streaming(chunk_text, i, tts_engine, tts_options or {})

            if not audio_path:
                error_info = {"error": "Audio generation failed", "status": "failed"}
                error_json = json.dumps(error_info)
                error_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(error_json)}\r\n\r\n{error_json}\r\n"
                yield error_frame.encode()
                return

            # Generate complete video using existing processors
            video_filename = f"realtime_{int(time.time())}.mp4"
            temp_video_path = f"/tmp/{video_filename}"

            success, duration = self._generate_video_with_processors(
                prompt, 0, source_image, audio_path, temp_video_path, options, audio_duration
            )

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
                yield b''

                # Stream video data in chunks
                with open(temp_video_path, 'rb') as video_file:
                    chunk_size = 64 * 1024
                    chunk_id = 0

                    while True:
                        video_chunk = video_file.read(chunk_size)
                        if not video_chunk:
                            break

                        video_base64 = base64.b64encode(video_chunk).decode('utf-8')

                        chunk_info = {
                            "chunk_id": chunk_id,
                            "data": video_base64,
                            "size": len(video_chunk),
                            "mode": "realtime"
                        }

                        chunk_json = json.dumps(chunk_info)
                        chunk_response = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(chunk_json)}\r\n\r\n{chunk_json}\r\n"
                        yield chunk_response.encode()
                        yield b''

                        chunk_id += 1
                        time.sleep(0.05)

                if os.path.exists(temp_video_path):
                    os.unlink(temp_video_path)

            if audio_path and os.path.exists(audio_path):
                os.unlink(audio_path)

            yield b"--frame--\r\n"
            logger.info("✅ Realtime stream completed")

        except Exception as e:
            logger.error(f"Realtime stream failed: {e}")
            error_info = {"error": str(e), "status": "failed"}
            error_json = json.dumps(error_info)
            error_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(error_json)}\r\n\r\n{error_json}\r\n"
            yield error_frame.encode()
            yield b"--frame--\r\n"

        finally:
            self.is_streaming = False

    def generate_nvidia_stream(self, options: Dict, prompt: str, source_image: np.ndarray,
                                 tts_engine: str = 'espeak', tts_options: Dict = None,
                                 codec: str = 'h264_fast', quality: str = 'medium',
                                 frame_rate: int = 30, buffer_size: int = 5, mode: str = 'auto',
                                 timeout: int = 300) -> Generator[bytes, None, None]:
        """Generate realtime stream using existing processors"""
        import base64

        logger.info(f"Starting realtime stream - Mode: {mode}, FPS: {frame_rate}")

        try:
            self.is_streaming = True

            # Generate audio for entire prompt using existing processors
            audio_path, audio_duration = self._generate_audio_with_processors(
                prompt, 0, tts_engine, tts_options or {}
            )

            if not audio_path:
                error_info = {"error": "Audio generation failed", "status": "failed"}
                error_json = json.dumps(error_info)
                error_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(error_json)}\r\n\r\n{error_json}\r\n"
                yield error_frame.encode()
                return

            # Generate complete video using existing processors
            video_filename = f"realtime_{int(time.time())}.mp4"
            temp_video_path = f"/tmp/{video_filename}"

            success, duration = self._generate_video_with_processors(
                prompt, 0, source_image, audio_path, temp_video_path, options, audio_duration
            )

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
                yield b''

                # Stream video data in chunks
                with open(temp_video_path, 'rb') as video_file:
                    chunk_size = 64 * 1024
                    chunk_id = 0

                    while True:
                        video_chunk = video_file.read(chunk_size)
                        if not video_chunk:
                            break

                        video_base64 = base64.b64encode(video_chunk).decode('utf-8')

                        chunk_info = {
                            "chunk_id": chunk_id,
                            "data": video_base64,
                            "size": len(video_chunk),
                            "mode": "realtime"
                        }

                        chunk_json = json.dumps(chunk_info)
                        chunk_response = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(chunk_json)}\r\n\r\n{chunk_json}\r\n"
                        yield chunk_response.encode()
                        yield b''

                        chunk_id += 1
                        time.sleep(0.05)

                if os.path.exists(temp_video_path):
                    os.unlink(temp_video_path)

            if audio_path and os.path.exists(audio_path):
                os.unlink(audio_path)

            yield b"--frame--\r\n"
            logger.info("✅ Realtime stream completed")

        except Exception as e:
            logger.error(f"Realtime stream failed: {e}")
            error_info = {"error": str(e), "status": "failed"}
            error_json = json.dumps(error_info)
            error_frame = f"--frame\r\nContent-Type: application/json\r\nContent-Length: {len(error_json)}\r\n\r\n{error_json}\r\n"
            yield error_frame.encode()
            yield b"--frame--\r\n"

        finally:
            self.is_streaming = False



