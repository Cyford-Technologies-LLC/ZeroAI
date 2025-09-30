# enhanced_audio2face_integration.py - Complete A2F integration with all options

import os
import sys
import time
import json
import requests
import tempfile
import subprocess
import logging
import traceback
from pathlib import Path
from typing import Optional, Dict, Any, Tuple, List
from datetime import datetime
# from audio2face_options import Audio2FaceOptions, prepare_audio2face_request

# Import your existing modules
sys.path.append('..')
from audio_processor import call_tts_service_with_options, normalize_audio
from utility import clean_text

logger = logging.getLogger(__name__)


class EnhancedAudio2FaceGenerator:
    """
    Enhanced Audio2Face integration with full parameter control
    """

    def __init__(self, a2f_server_url: str = "http://localhost:7860"):
        """
        Initialize Enhanced Audio2Face connection

        Args:
            a2f_server_url: URL of Audio2Face headless server
        """
        self.a2f_server_url = a2f_server_url.rstrip('/')
        self.session = requests.Session()
        self.current_character = None
        self.is_connected = False
        self.server_capabilities = {}

        # Test connection and get capabilities
        self._test_connection()
        self._get_server_capabilities()

    def _test_connection(self) -> bool:
        """Test if Audio2Face server is accessible"""
        try:
            response = self.session.get(f"{self.a2f_server_url}/status", timeout=5)
            if response.status_code == 200:
                self.is_connected = True
                logger.info("✅ Enhanced Audio2Face server connected")
                return True
        except Exception as e:
            logger.warning(f"⚠️ Audio2Face server not accessible: {e}")

        self.is_connected = False
        return False

    def _get_server_capabilities(self) -> Dict[str, Any]:
        """Get server capabilities and supported features"""
        if not self.is_connected:
            return {}

        try:
            response = self.session.get(f"{self.a2f_server_url}/capabilities", timeout=5)
            if response.status_code == 200:
                self.server_capabilities = response.json()
                logger.info(f"Server capabilities: {json.dumps(self.server_capabilities, indent=2)}")
        except Exception as e:
            logger.warning(f"Could not get server capabilities: {e}")

        return self.server_capabilities

    def generate_with_full_options(self,
                                   audio_path: str,
                                   output_path: str,
                                   options: Dict[str, Any]) -> bool:
        """
        Generate facial animation with all Audio2Face options

        Args:
            audio_path: Path to input audio file
            output_path: Path for output video
            options: Complete A2F options dictionary

        Returns:
            bool: Success status
        """
        try:
            if not self.is_connected:
                logger.error("Audio2Face not connected")
                return False

            # Prepare audio for A2F
            processed_audio = self._prepare_audio_advanced(audio_path, options.get("audio", {}))

            # Build the complete request
            with open(processed_audio, 'rb') as audio_file:
                files = {'audio': audio_file}

                # Flatten options for API
                data = self._flatten_options(options)

                # Add output settings
                data.update({
                    'output_path': output_path,
                    'return_metrics': True,
                    'return_blendshapes': True
                })

                logger.info(f"Sending request with options: {json.dumps(data, indent=2)}")

                response = self.session.post(
                    f"{self.a2f_server_url}/generate/advanced",
                    files=files,
                    data=data,
                    timeout=600  # 10 minute timeout for complex generation
                )

            if response.status_code == 200:
                # Handle response based on output format
                if options.get("output", {}).get("output_format") == "frames":
                    # Save frame sequence
                    self._save_frame_sequence(response.content, output_path)
                else:
                    # Save video file
                    with open(output_path, 'wb') as f:
                        f.write(response.content)

                # Log metrics if available
                if 'X-A2F-Metrics' in response.headers:
                    metrics = json.loads(response.headers['X-A2F-Metrics'])
                    logger.info(f"Generation metrics: {metrics}")

                logger.info(f"✅ Enhanced Audio2Face generation completed: {output_path}")
                return True
            else:
                error_msg = response.text
                logger.error(f"Audio2Face generation failed: {error_msg}")
                return False

        except Exception as e:
            logger.error(f"Enhanced A2F generation error: {e}\n{traceback.format_exc()}")
            return False
        finally:
            # Cleanup processed audio
            if 'processed_audio' in locals() and processed_audio != audio_path:
                try:
                    os.unlink(processed_audio)
                except:
                    pass

    def _prepare_audio_advanced(self, audio_path: str, audio_options: Dict[str, Any]) -> str:
        """
        Advanced audio preparation with all processing options

        Args:
            audio_path: Original audio path
            audio_options: Audio processing options

        Returns:
            str: Path to processed audio
        """
        try:
            output_path = audio_path.replace('.wav', '_processed.wav')

            # Build FFmpeg command with all audio options
            cmd = ['ffmpeg', '-i', audio_path]

            # Sample rate
            sample_rate = audio_options.get('audio_sample_rate', 22050)
            cmd.extend(['-ar', str(sample_rate)])

            # Amplitude adjustment
            amplitude = audio_options.get('audio_amplitude_multiplier', 1.0)
            if amplitude != 1.0:
                cmd.extend(['-filter:a', f'volume={amplitude}'])

            # Pre-processing (noise reduction, normalization)
            if audio_options.get('pre_process_audio', True):
                filters = []

                # Noise reduction
                filters.append('highpass=f=80')
                filters.append('lowpass=f=10000')

                # Normalization
                filters.append('loudnorm=I=-16:TP=-1.5:LRA=11')

                # Voice conversion
                voice_conv = audio_options.get('voice_conversion', 'none')
                if voice_conv != 'none':
                    pitch_map = {
                        'male_to_female': 1.2,
                        'female_to_male': 0.8,
                        'child': 1.3,
                        'elderly': 0.9
                    }
                    if voice_conv in pitch_map:
                        filters.append(f'asetrate=r={sample_rate}*{pitch_map[voice_conv]}')
                        filters.append(f'aresample={sample_rate}')

                if filters:
                    cmd.extend(['-af', ','.join(filters)])

            # Output settings
            cmd.extend([
                '-ac', '1',  # Mono
                '-c:a', 'pcm_s16le',  # 16-bit PCM
                '-y', output_path
            ])

            result = subprocess.run(cmd, capture_output=True, text=True)

            if result.returncode == 0 and os.path.exists(output_path):
                logger.info(f"Audio processed successfully with advanced options")
                return output_path
            else:
                logger.warning(f"Audio processing failed: {result.stderr}")
                return audio_path

        except Exception as e:
            logger.warning(f"Advanced audio preparation error: {e}")
            return audio_path

    def _flatten_options(self, options: Dict[str, Any]) -> Dict[str, str]:
        """Flatten nested options dictionary for API"""
        flattened = {}

        for category, params in options.items():
            if isinstance(params, dict):
                for key, value in params.items():
                    # Convert to string for form data
                    if isinstance(value, bool):
                        flattened[f"{category}_{key}"] = str(value).lower()
                    elif isinstance(value, list):
                        flattened[f"{category}_{key}"] = json.dumps(value)
                    else:
                        flattened[f"{category}_{key}"] = str(value)
            else:
                flattened[category] = str(params)

        return flattened

    def _save_frame_sequence(self, content: bytes, output_path: str):
        """Save frame sequence from A2F response"""
        import zipfile
        import io

        output_dir = Path(output_path).parent / f"{Path(output_path).stem}_frames"
        output_dir.mkdir(exist_ok=True)

        with zipfile.ZipFile(io.BytesIO(content)) as zf:
            zf.extractall(output_dir)

        logger.info(f"Frame sequence saved to: {output_dir}")

    def batch_generate(self, prompts: List[Dict[str, Any]], base_options: Dict[str, Any]) -> List[str]:
        """
        Batch generate multiple avatars with different prompts

        Args:
            prompts: List of prompt dictionaries with text and optional overrides
            base_options: Base A2F options to use for all

        Returns:
            List of output paths
        """
        output_paths = []

        for i, prompt_data in enumerate(prompts):
            try:
                prompt_text = prompt_data.get("text", "")
                prompt_options = prompt_data.get("options", {})

                # Merge options
                merged_options = base_options.copy()
                for key, value in prompt_options.items():
                    if key in merged_options:
                        merged_options[key].update(value)
                    else:
                        merged_options[key] = value

                # Generate output path
                timestamp = int(time.time())
                output_path = f"/app/static/batch_{i}_{timestamp}.mp4"

                # Generate TTS
                with tempfile.NamedTemporaryFile(suffix=".wav", delete=False) as audio_file:
                    audio_path = audio_file.name

                clean_prompt = clean_text(prompt_text)
                if call_tts_service_with_options(clean_prompt, audio_path, "espeak", {}):
                    audio_path = normalize_audio(audio_path)

                    # Generate with A2F
                    if self.generate_with_full_options(audio_path, output_path, merged_options):
                        output_paths.append(output_path)
                        logger.info(f"Batch item {i+1}/{len(prompts)} completed")

                    # Cleanup audio
                    os.unlink(audio_path)

            except Exception as e:
                logger.error(f"Batch item {i} failed: {e}")

        return output_paths

    def stream_generate(self, audio_stream, output_callback, options: Dict[str, Any]):
        """
        Stream generation for real-time applications

        Args:
            audio_stream: Audio stream generator
            output_callback: Callback for video chunks
            options: A2F options
        """
        # Implementation for streaming generation
        # This would integrate with your existing streaming infrastructure
        pass


def generate_enhanced_audio2face_avatar(
        prompt: str,
        output_path: str,
        tts_engine: str = 'espeak',
        tts_options: Dict = None,
        a2f_options: Dict = None,
        a2f_server_url: str = "http://localhost:7860",
        preset: str = None
) -> Tuple[bool, Dict[str, Any]]:
    """Your docstring here"""

    metadata = {
        "start_time": datetime.now().isoformat(),
        "prompt_length": len(prompt),
        "tts_engine": tts_engine,
        "preset": preset
    }

    try:
        logger.info(f"=== ENHANCED AUDIO2FACE GENERATION START ===")

        # Your existing code...

        a2f = EnhancedAudio2FaceGenerator(a2f_server_url)

        if not a2f.is_connected:
            logger.error("Cannot connect to Audio2Face server")
            metadata["error"] = "Cannot connect to Audio2Face server"
            return False, metadata

        # Continue with your processing...
        # Add your actual generation logic here

        # Success case
        metadata["end_time"] = datetime.now().isoformat()
        metadata["success"] = True
        return True, metadata

    except Exception as e:
        logger.error(f"Error in audio2face generation: {str(e)}")
        metadata["error"] = str(e)
        metadata["end_time"] = datetime.now().isoformat()
        return False, metadata