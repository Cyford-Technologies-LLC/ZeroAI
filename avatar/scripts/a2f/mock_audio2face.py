# mock_audio2face.py - Test Audio2Face integration without actual A2F server

import os
import time
import tempfile
import subprocess
import logging
from pathlib import Path

from audio_processor import call_tts_service_with_options, normalize_audio
from utility import clean_text

logger = logging.getLogger(__name__)


class MockAudio2FaceGenerator:
    """
    Mock Audio2Face that creates placeholder videos for testing integration.
    Use this while setting up the real Audio2Face server.
    """

    def __init__(self, a2f_server_url: str = "mock://localhost:7860"):
        self.a2f_server_url = a2f_server_url
        self.current_character = "MockCharacter_Female_01"
        self.is_connected = True
        logger.info("🔄 Mock Audio2Face initialized (for testing)")

    def load_character(self, character_path: str) -> bool:
        """Mock character loading"""
        self.current_character = character_path
        logger.info(f"🔄 Mock: Character loaded: {character_path}")
        return True

    def generate_facial_animation(self, audio_path: str, output_path: str,
                                  character_path: str = None) -> bool:
        """
        Generate a mock video with audio for testing.
        Creates a simple animated placeholder while preserving your audio.
        """
        try:
            if character_path:
                self.current_character = character_path

            logger.info(f"🔄 Mock Audio2Face generating video for character: {self.current_character}")

            # Get audio duration for video length
            result = subprocess.run([
                'ffprobe', '-v', 'quiet', '-show_entries', 'format=duration',
                '-of', 'csv=p=0', audio_path
            ], capture_output=True, text=True)

            duration = float(result.stdout.strip()) if result.returncode == 0 else 3.0

            # Create a simple animated video with your audio
            # This simulates what Audio2Face would do but with basic animation
            cmd = [
                'ffmpeg', '-y',

                # Video: Create animated talking head placeholder
                '-f', 'lavfi', '-i', f'testsrc2=size=512x512:rate=30:duration={duration}',
                '-f', 'lavfi', '-i', f'sine=frequency=440:duration={duration}',  # Dummy audio to sync

                # Your actual audio
                '-i', audio_path,

                # Video filters for mock "talking" animation
                '-filter_complex', '''[0:v]
                    drawtext=text='MOCK AUDIO2FACE':x=10:y=10:fontsize=24:fontcolor=white:box=1:boxcolor=black@0.8,
                    drawtext=text='Character\\: ''' + self.current_character.replace(':', '\\:') + '''':x=10:y=50:fontsize=16:fontcolor=yellow:box=1:boxcolor=black@0.8,
                    drawtext=text='Audio2Face Simulation':x=10:y=480:fontsize=20:fontcolor=green:box=1:boxcolor=black@0.8,
                    drawbox=x=150:y=150:w=200:h=250:color=lightblue@0.3:t=5,
                    drawtext=text='👤':x=230:y=250:fontsize=80,
                    scale=512:512[v]''',

                # Map the real audio
                '-map', '[v]', '-map', '2:a',

                # Encoding settings
                '-c:v', 'libx264', '-preset', 'fast', '-crf', '23',
                '-c:a', 'aac', '-b:a', '128k',
                '-pix_fmt', 'yuv420p',
                '-movflags', '+faststart',

                output_path
            ]

            result = subprocess.run(cmd, capture_output=True, text=True)

            if result.returncode == 0 and os.path.exists(output_path):
                logger.info(f"✅ Mock Audio2Face video created: {output_path}")
                return True
            else:
                logger.error(f"Mock video creation failed: {result.stderr}")
                return False

        except Exception as e:
            logger.error(f"Mock Audio2Face error: {e}")
            return False

    def list_available_characters(self) -> list:
        """Return mock character list"""
        return [
            "MockCharacter_Female_01",
            "MockCharacter_Male_01",
            "MockCharacter_Female_02",
            "TestCharacter_Realistic"
        ]


def generate_mock_audio2face_avatar(prompt: str, source_image: str, output_path: str,
                                    tts_engine: str = 'espeak', tts_options: dict = None,
                                    character_path: str = None) -> bool:
    """
    Generate mock Audio2Face avatar for testing integration
    """
    try:
        logger.info(f"🔄 Mock Audio2Face Generation: {prompt[:50]}...")

        # Use mock generator
        mock_a2f = MockAudio2FaceGenerator()

        # Generate TTS audio using existing system
        with tempfile.NamedTemporaryFile(suffix=".wav", delete=False) as audio_file:
            audio_path = audio_file.name

        clean_prompt = clean_text(prompt)
        if not call_tts_service_with_options(clean_prompt, audio_path, tts_engine, tts_options or {}):
            logger.error("TTS generation failed")
            return False

        # Normalize audio
        audio_path = normalize_audio(audio_path)

        # Generate mock video
        success = mock_a2f.generate_facial_animation(
            audio_path=audio_path,
            output_path=output_path,
            character_path=character_path or "MockCharacter_Female_01"
        )

        return success

    except Exception as e:
        logger.error(f"Mock Audio2Face error: {e}")
        return False
    finally:
        # Cleanup
        try:
            if 'audio_path' in locals() and os.path.exists(audio_path):
                os.unlink(audio_path)
        except:
            pass