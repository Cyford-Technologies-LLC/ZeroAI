#!/usr/bin/env python3
"""
Audio TTS Functions with Required Imports
"""

import asyncio
import logging
import numpy as np
import os
import queue
import re
import requests
import subprocess
import tempfile
import time
import unicodedata
import wave
from typing import Dict, Optional, Tuple, List
from utility import clean_text

# Third-party imports
try:
    from pydub import AudioSegment
    import edge_tts

    PYDUB_AVAILABLE = True
    EDGE_TTS_AVAILABLE = True
except ImportError as e:
    PYDUB_AVAILABLE = False
    EDGE_TTS_AVAILABLE = False
    print(f"Warning: Missing dependencies: {e}")

# Setup logger
logger = logging.getLogger(__name__)

# Configuration
TTS_API_URL = os.getenv('TTS_API_URL', 'http://tts:5000/synthesize')


def get_audio_duration(audio_path: str) -> float:
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


def call_tts_service_with_options(text: str, file_path: str, tts_engine: str = 'espeak',
                                  tts_options: Dict = None) -> bool:
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


def normalize_audio(audio_path: str) -> str:
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


def split_audio(audio_path: str, chunk_length_s: int = 10) -> List[str]:
    """Split audio into chunks"""
    try:
        if not PYDUB_AVAILABLE:
            logger.error("pydub required for audio splitting. Install with: pip install pydub")
            return [audio_path]

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


def generate_audio_for_streaming(text: str, chunk_id: int, tts_engine: str,
                                 tts_options: Dict) -> Tuple[Optional[str], float]:
    """Generate audio for streaming - returns (audio_path, duration)"""
    try:
        if tts_engine == 'edge':
            audio_path = _generate_edge_tts_chunk(text, chunk_id, tts_options)
        else:
            audio_path = _generate_espeak_chunk(text, chunk_id, tts_options)

        if audio_path and os.path.exists(audio_path):
            if PYDUB_AVAILABLE:
                audio = AudioSegment.from_file(audio_path)
                duration = len(audio) / 1000.0
            else:
                duration = get_audio_duration(audio_path)
            logger.info(f"Audio generated: {duration:.2f}s using {tts_engine}")
            return audio_path, duration
        else:
            return None, 0
    except Exception as e:
        logger.error(f"Audio generation failed: {e}")
        return None, 0


def split_by_duration(text: str, target_duration: float) -> List[str]:
    """Split text by estimated duration (words per second)"""
    words_per_second = 2.5  # Average speaking rate
    words_per_chunk = max(1, int(target_duration * words_per_second))

    words = text.split()
    chunks = []

    for i in range(0, len(words), words_per_chunk):
        chunk_words = words[i:i + words_per_chunk]
        chunks.append(' '.join(chunk_words))

    return chunks if chunks else [text]


def _split_into_sentences(text: str) -> List[str]:
    """Split text into sentences for chunking"""
    # Simple sentence splitting
    sentences = re.split(r'(?<=[.!?])\s+', text)

    # Filter out empty sentences
    sentences = [s.strip() for s in sentences if s.strip()]

    # If no sentences found, split by length
    if not sentences:
        words = text.split()
        chunk_size = max(10, len(words) // 3)  # At least 10 words per chunk
        sentences = []

        for i in range(0, len(words), chunk_size):
            chunk = ' '.join(words[i:i + chunk_size])
            if chunk:
                sentences.append(chunk)

    return sentences or [text]  # Return full text if no splitting possible


def _generate_edge_tts_chunk(text: str, chunk_id: int, options: Dict) -> Optional[str]:
    """Generate audio using Edge TTS for a chunk"""
    if not EDGE_TTS_AVAILABLE:
        logger.error("edge-tts not available. Install with: pip install edge-tts")
        return None

    async def generate_audio():
        voice = options.get('voice', 'en-US-AriaNeural')
        rate = options.get('speed', 0)
        pitch = options.get('pitch', 0)

        # Format rate and pitch for edge-tts
        rate_str = f"+{rate}%" if rate >= 0 else f"{rate}%"
        pitch_str = f"+{pitch}Hz" if pitch >= 0 else f"{pitch}Hz"

        output_path = f"/tmp/chunk_{chunk_id}_{int(time.time())}.mp3"

        communicate = edge_tts.Communicate(
            text,
            voice,
            rate=rate_str,
            pitch=pitch_str
        )

        await communicate.save(output_path)

        # Convert to WAV for SadTalker compatibility
        if PYDUB_AVAILABLE:
            audio = AudioSegment.from_mp3(output_path)
            wav_path = output_path.replace('.mp3', '.wav')
            audio.export(wav_path, format='wav')

            # Clean up MP3
            if os.path.exists(output_path):
                os.unlink(output_path)

            return wav_path
        else:
            return output_path

    try:
        # Run the async function
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        result = loop.run_until_complete(generate_audio())
        loop.close()

        return result

    except Exception as e:
        logger.error(f"Edge TTS generation failed: {e}")
        return None


def _generate_espeak_chunk(text: str, chunk_id: int, options: Dict) -> Optional[str]:
    """Generate audio using espeak for a chunk"""
    try:
        speed = options.get('speed', 175)
        pitch = options.get('pitch', 50)
        voice = options.get('voice', 'en')

        output_path = f"/tmp/chunk_{chunk_id}_{int(time.time())}.wav"

        cmd = [
            'espeak',
            '-v', voice,
            '-s', str(speed),
            '-p', str(pitch),
            '-w', output_path,
            text
        ]

        subprocess.run(cmd, check=True, capture_output=True)
        return output_path

    except Exception as e:
        logger.error(f"Espeak generation failed: {e}")
        return None


class TTSProcessor:
    """TTS Processor class for streaming operations"""

    def __init__(self, tts_api_url: str = None):
        self.tts_api_url = tts_api_url or TTS_API_URL

    def _call_tts_service(self, text: str, tts_engine: str = 'espeak',
                          tts_options: Dict = None) -> Optional[bytes]:
        """Call TTS service and return audio bytes"""
        try:
            payload = {'text': text, 'engine': tts_engine}
            if tts_options:
                payload.update(tts_options)

            response = requests.post(self.tts_api_url, json=payload, timeout=30)
            if response.status_code == 200:
                return response.content
            else:
                logger.error(f"TTS error {response.status_code}: {response.text[:200]}")
                return None
        except Exception as e:
            logger.error(f"TTS call failed: {e}")
            return None

    def _process_audio_chunk(self, audio_bytes: bytes) -> Tuple[float, Optional[str]]:
        """Process audio bytes and return duration and temp file path"""
        try:
            with tempfile.NamedTemporaryFile(suffix=".wav", delete=False) as f:
                f.write(audio_bytes)
                temp_path = f.name

            # Normalize audio
            cmd = ["ffmpeg", "-y", "-i", temp_path, "-ac", "1", "-ar", "16000",
                   "-acodec", "pcm_s16le", f"{temp_path}_fixed.wav"]
            subprocess.run(cmd, check=True, capture_output=True)

            # Get duration
            with wave.open(f"{temp_path}_fixed.wav", 'rb') as wf:
                frames = wf.getnframes()
                rate = wf.getframerate()
                duration = frames / float(rate)

            os.unlink(temp_path)  # Remove original
            return duration, f"{temp_path}_fixed.wav"

        except Exception as e:
            logger.error(f"Audio processing failed: {e}")
            return 0.0, None


# Example usage
if __name__ == "__main__":
    print("Audio TTS functions loaded successfully")

    # Test text splitting
    test_text = "Hello world! This is a test. How are you today?"
    sentences = _split_into_sentences(test_text)
    print(f"Sentences: {sentences}")

    duration_chunks = split_by_duration(test_text, 3.0)
    print(f"Duration chunks: {duration_chunks}")