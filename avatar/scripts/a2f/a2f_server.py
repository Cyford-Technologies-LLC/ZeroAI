#!/usr/bin/env python3
"""
Audio2Face Headless Server
Provides REST API for facial animation generation
"""

import os
import sys
import json
import time
import logging
import tempfile
import subprocess
from pathlib import Path
from flask import Flask, request, jsonify, send_file
from flask_cors import CORS

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

app = Flask(__name__)
CORS(app)

# Audio2Face Kit application path (adjust based on installation)
A2F_APP_PATH = os.environ.get('A2F_APP_PATH', '/opt/nvidia/omniverse/audio2face')
A2F_USD_PATH = os.environ.get('A2F_USD_PATH', '/var/lib/audio2face/stages')

class Audio2FaceServer:
    """Audio2Face headless server implementation"""

    def __init__(self):
        self.kit_app = None
        self.current_stage = None
        self.characters = {}
        self.initialize()

    def initialize(self):
        """Initialize Audio2Face Kit application"""
        try:
            # Import Omniverse Kit
            sys.path.append(f"{A2F_APP_PATH}/kit/python")
            import omni.kit.app

            # Start Kit application in headless mode
            self.kit_app = omni.kit.app.get_app()

            # Load Audio2Face extension
            manager = self.kit_app.get_extension_manager()
            manager.set_extension_enabled("omni.audio2face", True)
            manager.set_extension_enabled("omni.audio2face.headless", True)

            logger.info("Audio2Face Kit application initialized")

            # Load default character
            self.load_default_character()

        except Exception as e:
            logger.error(f"Failed to initialize Audio2Face: {e}")
            # Fallback to command-line mode
            self.use_cli_mode()

    def use_cli_mode(self):
        """Fallback to command-line Audio2Face"""
        logger.info("Using Audio2Face CLI mode")
        self.cli_mode = True

    def load_default_character(self):
        """Load default character model"""
        try:
            default_char = f"{A2F_USD_PATH}/default_character.usd"
            if os.path.exists(default_char):
                self.load_character(default_char)
            else:
                logger.warning("Default character not found")
        except Exception as e:
            logger.error(f"Failed to load default character: {e}")

    def load_character(self, usd_path):
        """Load character from USD file"""
        if self.kit_app:
            import omni.usd
            stage = omni.usd.get_context().get_stage()
            stage.Load(usd_path)
            self.current_stage = stage
            return True
        return False

    def generate_animation(self, audio_path, options=None):
        """Generate facial animation from audio"""
        try:
            if options is None:
                options = {}

            output_path = options.get('output_path', '/tmp/a2f_output.mp4')

            if self.cli_mode:
                # Use command-line interface
                cmd = [
                    f"{A2F_APP_PATH}/audio2face_headless",
                    "--input", audio_path,
                    "--output", output_path,
                    "--character", options.get('character', 'default'),
                    "--quality", options.get('quality', 'high'),
                    "--fps", str(options.get('fps', 30))
                ]

                # Add emotion parameters if specified
                if 'emotion' in options:
                    cmd.extend(["--emotion", options['emotion']])

                result = subprocess.run(cmd, capture_output=True, text=True)

                if result.returncode == 0:
                    return output_path
                else:
                    logger.error(f"A2F CLI error: {result.stderr}")
                    return None
            else:
                # Use Kit API
                import omni.audio2face

                # Process through Audio2Face
                processor = omni.audio2face.get_processor()
                processor.set_audio(audio_path)
                processor.set_options(options)
                processor.process()

                # Export animation
                exporter = omni.audio2face.get_exporter()
                exporter.export_video(output_path, fps=options.get('fps', 30))

                return output_path

        except Exception as e:
            logger.error(f"Animation generation failed: {e}")
            return None

# Initialize server
a2f_server = Audio2FaceServer()

@app.route('/status')
def status():
    """Check server status"""
    return jsonify({
        'status': 'running',
        'mode': 'cli' if hasattr(a2f_server, 'cli_mode') else 'kit',
        'version': '2023.2.0',
        'gpu': os.environ.get('CUDA_VISIBLE_DEVICES', 'auto'),
        'port': 7860
    })

@app.route('/generate', methods=['POST'])
def generate():
    """Generate facial animation from audio"""
    try:
        # Get audio file
        if 'audio' not in request.files:
            return jsonify({'error': 'No audio file provided'}), 400

        audio_file = request.files['audio']

        # Save audio temporarily
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as tmp:
            audio_file.save(tmp.name)
            audio_path = tmp.name

        # Get options from form data
        options = {
            'character': request.form.get('character', 'default'),
            'fps': int(request.form.get('fps', 30)),
            'quality': request.form.get('quality', 'high'),
            'emotion': request.form.get('emotion_type', 'neutral'),
            'emotion_intensity': float(request.form.get('emotion_intensity', 0.5))
        }

        # Generate animation
        output_path = a2f_server.generate_animation(audio_path, options)

        if output_path and os.path.exists(output_path):
            return send_file(output_path, mimetype='video/mp4')
        else:
            return jsonify({'error': 'Generation failed'}), 500

    except Exception as e:
        logger.error(f"Generate endpoint error: {e}")
        return jsonify({'error': str(e)}), 500
    finally:
        # Cleanup
        if 'audio_path' in locals():
            try:
                os.unlink(audio_path)
            except:
                pass

@app.route('/characters')
def list_characters():
    """List available characters"""
    characters = []

    # Check USD directory for characters
    usd_dir = Path(A2F_USD_PATH)
    if usd_dir.exists():
        for usd_file in usd_dir.glob("*.usd"):
            characters.append(usd_file.stem)

    # Add default characters
    default_chars = ['james', 'claire', 'mark', 'allison']
    characters.extend(default_chars)

    return jsonify({
        'characters': list(set(characters)),
        'current': 'default'
    })

@app.route('/character/load', methods=['POST'])
def load_character():
    """Load a specific character"""
    data = request.get_json()
    character_path = data.get('character_path')

    if not character_path:
        return jsonify({'error': 'No character path provided'}), 400

    success = a2f_server.load_character(character_path)

    if success:
        return jsonify({'status': 'loaded', 'character': character_path})
    else:
        return jsonify({'error': 'Failed to load character'}), 500

if __name__ == '__main__':
    logger.info("Starting Audio2Face Headless Server on port 7860")
    app.run(host='0.0.0.0', port=7860, debug=False)