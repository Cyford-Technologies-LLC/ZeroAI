# audio2face_integration.py - Simple A2F integration for your existing avatar system

import os
import time
import json
import requests
import tempfile
import subprocess
import logging
from pathlib import Path
from typing import Optional, Dict, Any, Tuple



# Your existing imports
from audio_processor import call_tts_service_with_options, normalize_audio
from utility import clean_text

logger = logging.getLogger(__name__)


class Audio2FaceGenerator:
    """
    Simple Audio2Face integration that works with your existing TTS system.
    Uses NVIDIA's A2F REST API for facial animation generation.
    """

    def __init__(self, a2f_server_url: str = "http://localhost:8011"):
        """
        Initialize Audio2Face connection

        Args:
            a2f_server_url: URL of Audio2Face headless server (default localhost:8011)
        """
        self.a2f_server_url = a2f_server_url.rstrip('/')
        self.session = requests.Session()
        self.current_character = None
        self.is_connected = False

        # Test connection
        self._test_connection()

    def _test_connection(self) -> bool:
        """Test if Audio2Face server is accessible"""
        try:
            response = self.session.get(f"{self.a2f_server_url}/status", timeout=5)
            if response.status_code == 200:
                self.is_connected = True
                logger.info("✅ Audio2Face server connected successfully")
                return True
        except Exception as e:
            logger.warning(f"⚠️ Audio2Face server not accessible: {e}")
            logger.warning("Make sure Audio2Face is running with headless mode enabled")

        self.is_connected = False
        return False

    def load_character(self, character_path: str) -> bool:
        """
        Load a character model into Audio2Face

        Args:
            character_path: Path to USD character file or character name in A2F

        Returns:
            bool: Success status
        """
        try:
            if not self.is_connected:
                logger.error("Audio2Face not connected")
                return False

            payload = {
                "character_path": character_path
            }

            response = self.session.post(
                f"{self.a2f_server_url}/character/load",
                json=payload,
                timeout=30
            )

            if response.status_code == 200:
                self.current_character = character_path
                logger.info(f"✅ Character loaded: {character_path}")
                return True
            else:
                logger.error(f"Failed to load character: {response.text}")
                return False

        except Exception as e:
            logger.error(f"Character loading error: {e}")
            return False

    def generate_facial_animation(self, audio_path: str, output_path: str,
                                  character_path: Optional[str] = None) -> bool:
        """
        Generate facial animation from audio using Audio2Face

        Args:
            audio_path: Path to input audio file (WAV format)
            output_path: Path for output video file
            character_path: Optional character to use (if not already loaded)

        Returns:
            bool: Success status
        """
        try:
            if not self.is_connected:
                logger.error("Audio2Face not connected")
                return False

            # Load character if specified
            if character_path and character_path != self.current_character:
                if not self.load_character(character_path):
                    return False

            # Ensure we have a character loaded
            if not self.current_character:
                logger.error("No character loaded in Audio2Face")
                return False

            # Convert audio to format A2F expects if needed
            processed_audio_path = self._prepare_audio(audio_path)

            # Send audio for processing
            with open(processed_audio_path, 'rb') as audio_file:
                files = {'audio': audio_file}
                data = {
                    'character': self.current_character,
                    'output_format': 'mp4',
                    'fps': 30
                }

                response = self.session.post(
                    f"{self.a2f_server_url}/generate",
                    files=files,
                    data=data,
                    timeout=300  # 5 minute timeout for generation
                )

            if response.status_code == 200:
                # Save the generated video
                with open(output_path, 'wb') as output_file:
                    output_file.write(response.content)

                logger.info(f"✅ Audio2Face generation completed: {output_path}")
                return True
            else:
                logger.error(f"Audio2Face generation failed: {response.text}")
                return False

        except Exception as e:
            logger.error(f"Audio2Face generation error: {e}")
            return False
        finally:
            # Cleanup processed audio if it's different from original
            if 'processed_audio_path' in locals() and processed_audio_path != audio_path:
                try:
                    os.unlink(processed_audio_path)
                except:
                    pass

    def _prepare_audio(self, audio_path: str) -> str:
        """
        Prepare audio file for Audio2Face (ensure correct format)

        Args:
            audio_path: Path to input audio

        Returns:
            str: Path to prepared audio file
        """
        try:
            # Check if audio is already in the right format
            result = subprocess.run([
                'ffprobe', '-v', 'quiet', '-print_format', 'json',
                '-show_format', '-show_streams', audio_path
            ], capture_output=True, text=True)

            if result.returncode == 0:
                info = json.loads(result.stdout)
                audio_stream = next((s for s in info['streams'] if s['codec_type'] == 'audio'), None)

                if (audio_stream and
                        audio_stream.get('codec_name') == 'pcm_s16le' and
                        audio_stream.get('sample_rate') == '22050'):
                    # Audio is already in correct format
                    return audio_path

            # Convert audio to A2F preferred format
            output_path = audio_path.replace('.wav', '_a2f.wav')

            cmd = [
                'ffmpeg', '-i', audio_path,
                '-ar', '22050',  # Sample rate
                '-ac', '1',  # Mono
                '-c:a', 'pcm_s16le',  # 16-bit PCM
                '-y', output_path
            ]

            result = subprocess.run(cmd, capture_output=True, text=True)

            if result.returncode == 0 and os.path.exists(output_path):
                return output_path
            else:
                logger.warning(f"Audio conversion failed, using original: {result.stderr}")
                return audio_path

        except Exception as e:
            logger.warning(f"Audio preparation error: {e}, using original")
            return audio_path

    def list_available_characters(self) -> list:
        """Get list of available characters from Audio2Face"""
        try:
            if not self.is_connected:
                return []

            response = self.session.get(f"{self.a2f_server_url}/characters")

            if response.status_code == 200:
                return response.json().get('characters', [])
            else:
                logger.error(f"Failed to get characters list: {response.text}")
                return []

        except Exception as e:
            logger.error(f"Error getting characters list: {e}")
            return []


def generate_audio2face_avatar(prompt: str, source_image: str, output_path: str,
                               tts_engine: str = 'espeak', tts_options: Dict = None,
                               character_path: str = None,
                               a2f_server_url: str = "http://localhost:8011") -> bool:
    """
    Complete pipeline: TTS -> Audio2Face -> Video

    Args:
        prompt: Text to speak
        source_image: Path to source image or character reference
        output_path: Path for output video
        tts_engine: TTS engine to use ('espeak', 'edge', etc.)
        tts_options: TTS options (voice, rate, etc.)
        character_path: Audio2Face character to use
        a2f_server_url: Audio2Face server URL

    Returns:
        bool: Success status
    """
    try:
        logger.info(f"=== AUDIO2FACE GENERATION START ===")
        logger.info(f"Prompt: {prompt[:50]}...")
        logger.info(f"Character: {character_path}")

        # Initialize Audio2Face
        a2f = Audio2FaceGenerator(a2f_server_url)

        if not a2f.is_connected:
            logger.error("Cannot connect to Audio2Face server")
            return False

        # Generate TTS audio using your existing system
        with tempfile.NamedTemporaryFile(suffix=".wav", delete=False) as audio_file:
            audio_path = audio_file.name

        # Clean text and generate TTS
        clean_prompt = clean_text(prompt)
        if not call_tts_service_with_options(clean_prompt, audio_path, tts_engine, tts_options or {}):
            logger.error("TTS generation failed")
            return False

        # Normalize audio
        audio_path = normalize_audio(audio_path)

        # Set default character if none provided
        if not character_path:
            # Try to find available characters
            characters = a2f.list_available_characters()
            if characters:
                character_path = characters[0]
                logger.info(f"Using default character: {character_path}")
            else:
                logger.error("No characters available in Audio2Face")
                return False

        # Generate facial animation
        success = a2f.generate_facial_animation(
            audio_path=audio_path,
            output_path=output_path,
            character_path=character_path
        )

        if success:
            logger.info(f"✅ Audio2Face video generated successfully: {output_path}")
        else:
            logger.error("Audio2Face generation failed")

        return success

    except Exception as e:
        logger.error(f"Audio2Face pipeline error: {e}")
        return False
    finally:
        # Cleanup temporary audio files
        try:
            if 'audio_path' in locals() and os.path.exists(audio_path):
                os.unlink(audio_path)
            if 'audio_path' in locals():
                normalized_path = audio_path.replace('.wav', '_fixed.wav')
                if os.path.exists(normalized_path):
                    os.unlink(normalized_path)
        except:
            pass


def check_audio2face_requirements() -> Dict[str, Any]:
    """
    Check if Audio2Face integration requirements are met

    Returns:
        Dict with status information
    """
    status = {
        'audio2face_available': False,
        'server_reachable': False,
        'characters_available': [],
        'requirements_met': False,
        'issues': []
    }

    try:
        # Test Audio2Face connection
        a2f = Audio2FaceGenerator()

        if a2f.is_connected:
            status['audio2face_available'] = True
            status['server_reachable'] = True

            # Get available characters
            characters = a2f.list_available_characters()
            status['characters_available'] = characters

            if characters:
                status['requirements_met'] = True
            else:
                status['issues'].append("No characters loaded in Audio2Face")
        else:
            status['issues'].append("Audio2Face server not reachable")

    except Exception as e:
        status['issues'].append(f"Audio2Face check failed: {str(e)}")

    # Check other requirements
    try:
        subprocess.run(['ffmpeg', '-version'], capture_output=True, check=True)
    except:
        status['issues'].append("FFmpeg not available")

    return status