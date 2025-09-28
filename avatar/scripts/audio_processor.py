#!/usr/bin/env python3
"""
Audio TTS Functions with Required Imports
"""

import unicodedata
import wave
import logging
import requests
import subprocess
import os
import tempfile
from typing import Dict, Optional, Tuple, List
from utility import clean_text
import time  # Add this - missing 'time'
import os    # Add this if not already present
from typing import List, Dict  # Add this - missing 'List' and 'Dict'
import logging

# Third-party imports (install with: pip install pydub)
try:
    from pydub import AudioSegment

    PYDUB_AVAILABLE = True
except ImportError:
    PYDUB_AVAILABLE = False
    print("Warning: pydub not available. Install with: pip install pydub")

# Setup logger
logger = logging.getLogger(__name__)

# Configuration - update with your TTS API URL

TTS_API_URL = os.getenv('TTS_API_URL', 'http://tts:5000/synthesize')




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


def split_audio(audio_path, chunk_length_s=10):
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


class TTSProcessor:
    """TTS Processor class for the class methods"""

    def __init__(self, tts_api_url=None):
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

    def _process_audio_chunk(self, audio_bytes: bytes) -> Tuple[float, str]:
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


def _generate_espeak_chunk(text: str, chunk_id: int, options: Dict) -> str:
    """Generate audio using espeak for a chunk"""
    import subprocess

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


def _split_into_sentences(text: str) -> List[str]:
    """Split text into sentences for chunking"""
    import re

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


def _split_into_sentences(text: str) -> List[str]:
    """Split text into sentences for chunking"""
    import re

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

# Example usage
if __name__ == "__main__":
    # Test the functions
    print("Audio TTS functions loaded successfully")

    # Test text cleaning
    test_text = "  Hello    world!  \n\t  "
    cleaned = clean_text(test_text)
    print(f"Cleaned text: '{cleaned}'")