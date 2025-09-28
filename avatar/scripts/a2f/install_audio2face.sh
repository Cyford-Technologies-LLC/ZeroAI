#!/bin/bash
# install_audio2face.sh - Complete Audio2Face integration installer

set -e

echo "=== AUDIO2FACE INTEGRATION INSTALLER ==="
echo "This script will set up Audio2Face integration for your avatar system."
echo

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Check if we're in the right directory
check_environment() {
    print_step "Checking environment..."

    if [ ! -f "avatar_endpoints.py" ]; then
        print_error "avatar_endpoints.py not found. Please run this script from your avatar project directory."
        exit 1
    fi

    if [ ! -f "audio_processor.py" ]; then
        print_error "audio_processor.py not found. This script needs your existing avatar system."
        exit 1
    fi

    print_status "Environment check passed"
}

# Create backup
create_backup() {
    print_step "Creating backup..."

    BACKUP_DIR="backup_$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"

    # Backup key files
    for file in avatar_endpoints.py audio_processor.py; do
        if [ -f "$file" ]; then
            cp "$file" "$BACKUP_DIR/"
            print_status "Backed up $file"
        fi
    done

    print_status "Backup created in $BACKUP_DIR"
}

# Install Python dependencies
install_dependencies() {
    print_step "Installing Python dependencies..."

    # Check if pip is available
    if ! command -v pip &> /dev/null; then
        print_error "pip not found. Please install pip first."
        exit 1
    fi

    # Install required packages
    pip install requests

    print_status "Dependencies installed"
}

# Download integration files
download_files() {
    print_step "Installing Audio2Face integration files..."

    # Create the audio2face_integration.py file
    cat > audio2face_integration.py << 'EOF'
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
                '-ar', '22050',           # Sample rate
                '-ac', '1',               # Mono
                '-c:a', 'pcm_s16le',      # 16-bit PCM
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
EOF

    print_status "Created audio2face_integration.py"

    # Create the mock Audio2Face file
    cat > mock_audio2face.py << 'EOF'
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

    def __init__(self, a2f_server_url: str = "mock://localhost:8011"):
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
EOF

    print_status "Created mock_audio2face.py"
}

# Patch avatar_endpoints.py
patch_endpoints() {
    print_step "Patching avatar_endpoints.py..."

    # Create a patch file that adds Audio2Face endpoints
    cat > audio2face_endpoints_patch.py << 'EOF'
# Add this code to your avatar_endpoints.py file after your existing imports

import time
import traceback

# Import both real and mock Audio2Face
try:
    from audio2face_integration import (
        generate_audio2face_avatar,
        check_audio2face_requirements,
        Audio2FaceGenerator
    )
    AUDIO2FACE_REAL_AVAILABLE = True
except ImportError:
    AUDIO2FACE_REAL_AVAILABLE = False

try:
    from mock_audio2face import (
        generate_mock_audio2face_avatar,
        MockAudio2FaceGenerator
    )
    AUDIO2FACE_MOCK_AVAILABLE = True
except ImportError:
    AUDIO2FACE_MOCK_AVAILABLE = False

# ============================================================================
# AUDIO2FACE ENDPOINTS - Add these to your existing Flask app
# ============================================================================

@app.route('/generate/audio2face', methods=['POST'])
def generate_audio2face_avatar_endpoint():
    """Generate talking avatar video using NVIDIA Audio2Face (real or mock)."""
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
        force_mock = data.get("force_mock", False)

        # Handle TTS options
        tts_options = {}
        for key in ["voice", "rate", "pitch", "language"]:
            if key in data:
                tts_options[key] = data[key]

        # Determine which Audio2Face to use
        use_mock = force_mock
        mode_info = {"type": "unknown", "reason": ""}

        if not use_mock and AUDIO2FACE_REAL_AVAILABLE:
            try:
                status = check_audio2face_requirements()
                if status['requirements_met']:
                    use_mock = False
                    mode_info = {"type": "real", "reason": "Audio2Face server available"}
                else:
                    use_mock = True
                    mode_info = {"type": "mock", "reason": "Audio2Face server not ready"}
            except:
                use_mock = True
                mode_info = {"type": "mock", "reason": "Audio2Face check failed"}
        else:
            use_mock = True
            if force_mock:
                mode_info = {"type": "mock", "reason": "Forced mock mode"}
            elif not AUDIO2FACE_REAL_AVAILABLE:
                mode_info = {"type": "mock", "reason": "Real Audio2Face not installed"}

        logger.info(f"=== AUDIO2FACE GENERATION START ===")
        logger.info(f"Mode: {mode_info['type']} ({mode_info['reason']})")
        logger.info(f"Prompt: {prompt[:50]}...")

        # Generate output path
        timestamp = int(time.time())
        mode_prefix = "mock_" if use_mock else "real_"
        output_filename = f"audio2face_{mode_prefix}{timestamp}.mp4"
        output_path = f"/app/static/{output_filename}"

        # Generate avatar
        if use_mock:
            if not AUDIO2FACE_MOCK_AVAILABLE:
                return jsonify({
                    "error": "Neither real nor mock Audio2Face available"
                }), 503

            success = generate_mock_audio2face_avatar(
                prompt=prompt,
                source_image="",
                output_path=output_path,
                tts_engine=tts_engine,
                tts_options=tts_options,
                character_path=character_path
            )
        else:
            success = generate_audio2face_avatar(
                prompt=prompt,
                source_image="",
                output_path=output_path,
                tts_engine=tts_engine,
                tts_options=tts_options,
                character_path=character_path,
                a2f_server_url=data.get("a2f_server_url", "http://localhost:8011")
            )

        if success and os.path.exists(output_path):
            logger.info(f"✅ Audio2Face generation completed: {output_filename}")

            response = send_file(output_path, mimetype='video/mp4', as_attachment=False)
            response.headers['X-Audio2Face-Mode'] = mode_info['type']
            response.headers['X-Audio2Face-Reason'] = mode_info['reason']

            return response
        else:
            return jsonify({
                "error": f"Audio2Face generation failed ({mode_info['type']} mode)"
            }), 500

    except Exception as e:
        logger.error("Audio2Face generation error: %s\n%s", e, traceback.format_exc())
        return jsonify({"error": str(e)}), 500


@app.route('/audio2face/status')
def audio2face_status():
    """Check Audio2Face integration status"""
    try:
        status = {
            'timestamp': str(datetime.now()),
            'real_audio2face': {'available': False, 'status': {}},
            'mock_audio2face': {'available': AUDIO2FACE_MOCK_AVAILABLE},
            'recommended_mode': 'unknown'
        }

        if AUDIO2FACE_REAL_AVAILABLE:
            try:
                real_status = check_audio2face_requirements()
                status['real_audio2face'] = {
                    'available': True,
                    'status': real_status
                }

                if real_status['requirements_met']:
                    status['recommended_mode'] = 'real'
                else:
                    status['recommended_mode'] = 'mock'
            except Exception as e:
                status['real_audio2face'] = {
                    'available': False,
                    'error': str(e)
                }
                status['recommended_mode'] = 'mock'
        else:
            status['recommended_mode'] = 'mock' if AUDIO2FACE_MOCK_AVAILABLE else 'none'

        return jsonify(status)

    except Exception as e:
        return jsonify({'error': str(e)}), 500


@app.route('/audio2face/characters')
def list_audio2face_characters():
    """List available characters from real or mock Audio2Face"""
    try:
        force_mock = request.args.get('force_mock', 'false').lower() == 'true'

        result = {
            'characters': [],
            'mode': 'unknown',
                                    'source': 'none'
        }

        # Try real Audio2Face first
        if not force_mock and AUDIO2FACE_REAL_AVAILABLE:
            try:
                a2f_server_url = request.args.get('server_url', 'http://localhost:8011')
                a2f = Audio2FaceGenerator(a2f_server_url)

                if a2f.is_connected:
                    characters = a2f.list_available_characters()
                    result = {
                        'characters': characters,
                        'count': len(characters),
                        'mode': 'real',
                        'source': a2f_server_url,
                        'current_character': a2f.current_character
                    }
                    return jsonify(result)
            except Exception as e:
                logger.warning(f"Real Audio2Face failed: {e}")

        # Fallback to mock
        if AUDIO2FACE_MOCK_AVAILABLE:
            mock_a2f = MockAudio2FaceGenerator()
            characters = mock_a2f.list_available_characters()
            result = {
                'characters': characters,
                'count': len(characters),
                'mode': 'mock',
                'source': 'mock_audio2face',
                'current_character': mock_a2f.current_character
            }
        else:
            result['error'] = 'No Audio2Face implementation available'

        return jsonify(result)

    except Exception as e:
        logger.error(f"Error listing characters: {e}")
        return jsonify({'error': str(e)}), 500

EOF

    print_status "Created endpoint patch file"
    print_warning "You'll need to manually add the endpoints to your avatar_endpoints.py file"
    print_warning "See audio2face_endpoints_patch.py for the code to add"
}

# Create test script
create_test_script() {
    print_step "Creating test script..."

    cat > test_audio2face.py << 'EOF'
#!/usr/bin/env python3
# test_audio2face.py - Quick test script

import requests
import json
import time

def test_integration():
    print("Testing Audio2Face Integration...")

    # Test status
    print("\n1. Checking status...")
    response = requests.get("http://localhost:7860/audio2face/status")
    if response.status_code == 200:
        status = response.json()
        print(f"   Recommended mode: {status.get('recommended_mode')}")

    # Test characters
    print("\n2. Listing characters...")
    response = requests.get("http://localhost:7860/audio2face/characters")
    if response.status_code == 200:
        chars = response.json()
        print(f"   Mode: {chars.get('mode')}")
        print(f"   Characters: {chars.get('characters', [])}")

    # Test generation
    print("\n3. Testing generation (mock mode)...")
    response = requests.post("http://localhost:7860/generate/audio2face", json={
        "prompt": "Hello! This is a test of Audio2Face integration.",
        "force_mock": True,
        "tts_engine": "espeak"
    }, stream=True)

    if response.status_code == 200:
        with open(f"test_audio2face_{int(time.time())}.mp4", "wb") as f:
            for chunk in response.iter_content(chunk_size=8192):
                f.write(chunk)
        print("   Success! Video saved.")
    else:
        print(f"   Failed: {response.status_code}")

if __name__ == "__main__":
    test_integration()
EOF

    chmod +x test_audio2face.py
    print_status "Created test_audio2face.py"
}

# Create documentation
create_docs() {
    print_step "Creating documentation..."

    cat > AUDIO2FACE_README.md << 'EOF'
# Audio2Face Integration

## Quick Start

1. **Test Mock Mode** (works immediately):
   ```bash
   python test_audio2face.py
   ```

2. **Check Integration Status**:
   ```bash
   curl http://localhost:7860/audio2face/status | jq
   ```

3. **Generate Avatar** (mock mode):
   ```bash
   curl -X POST http://localhost:7860/generate/audio2face \
     -H "Content-Type: application/json" \
     -d '{"prompt": "Hello world!", "force_mock": true}' \
     --output test.mp4
   ```

## Installation Steps for Real Audio2Face

1. **Install NVIDIA Omniverse**:
   - Download from https://www.nvidia.com/omniverse/
   - Install Audio2Face through Omniverse Launcher
   - Requires RTX GPU with 8GB+ VRAM

2. **Enable Headless Mode**:
   - Open Audio2Face
   - Go to Window > Extensions
   - Enable "Audio2Face Headless" extension
   - Server runs on localhost:8011

3. **Load Characters**:
   - Load character models in Audio2Face UI
   - Characters become available via API

4. **Test Real Mode**:
   ```bash
   curl -X POST http://localhost:7860/generate/audio2face \
     -H "Content-Type: application/json" \
     -d '{"prompt": "Real Audio2Face test!", "force_mock": false}' \
     --output real_test.mp4
   ```

## API Endpoints

- `/generate/audio2face` - Generate avatar video
- `/audio2face/status` - Check integration status
- `/audio2face/characters` - List available characters

## Modes

- **Mock Mode**: Works immediately, creates test videos
- **Real Mode**: Requires Audio2Face server, professional quality

The system automatically chooses the best available mode.
EOF

    print_status "Created AUDIO2FACE_README.md"
}

# Main installation flow
main() {
    echo "Starting Audio2Face integration installation..."
    echo

    check_environment
    create_backup
    install_dependencies
    download_files
    patch_endpoints
    create_test_script
    create_docs

    echo
    print_status "Installation completed successfully!"
    echo
    echo "Next steps:"
    echo "1. Add the endpoints from audio2face_endpoints_patch.py to your avatar_endpoints.py"
    echo "2. Restart your Flask server"
    echo "3. Run: python test_audio2face.py"
    echo "4. Check mock mode works, then install real Audio2Face if desired"
    echo
    echo "Files created:"
    echo "- audio2face_integration.py (real A2F integration)"
    echo "- mock_audio2face.py (mock A2F for testing)"
    echo "- audio2face_endpoints_patch.py (endpoints to add)"
    echo "- test_audio2face.py (test script)"
    echo "- AUDIO2FACE_README.md (documentation)"
    echo
    print_warning "Remember to manually add the endpoints to avatar_endpoints.py!"
}

# Run if script is executed directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi