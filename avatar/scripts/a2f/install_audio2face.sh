#!/bin/bash
# install_audio2face_server.sh - Complete Audio2Face server installation with headless service

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
A2F_INSTALL_DIR="/opt/audio2face"
A2F_DATA_DIR="/var/lib/audio2face"
A2F_LOG_DIR="/var/log/audio2face"
A2F_PORT="8011"
OMNIVERSE_CACHE="/var/cache/omniverse"
SERVICE_USER="audio2face"

# Functions
print_status() { echo -e "${GREEN}[✓]${NC} $1"; }
print_error() { echo -e "${RED}[✗]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[!]${NC} $1"; }
print_step() { echo -e "${BLUE}[→]${NC} $1"; }
print_info() { echo -e "${CYAN}[i]${NC} $1"; }

# Header
clear
echo "============================================"
echo "   NVIDIA AUDIO2FACE SERVER INSTALLATION"
echo "============================================"
echo ""

# Check prerequisites
check_prerequisites() {
    print_step "Checking prerequisites..."

    # Check if running as root
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root (use sudo)"
        exit 1
    fi

    # Check GPU
    if ! command -v nvidia-smi &> /dev/null; then
        print_error "NVIDIA GPU driver not found. Audio2Face requires NVIDIA GPU."
        print_info "Install NVIDIA drivers first: sudo apt install nvidia-driver-525"
        exit 1
    fi

    # Check GPU memory
    GPU_MEM=$(nvidia-smi --query-gpu=memory.total --format=csv,noheader,nounits | head -1)
    if [ "$GPU_MEM" -lt 8000 ]; then
        print_warning "GPU has less than 8GB VRAM. Audio2Face may have performance issues."
    else
        print_status "GPU check passed (${GPU_MEM}MB VRAM)"
    fi

    # Check Docker
    if ! command -v docker &> /dev/null; then
        print_warning "Docker not found. Installing Docker..."
        curl -fsSL https://get.docker.com -o get-docker.sh
        sh get-docker.sh
        rm get-docker.sh
    fi

    print_status "Prerequisites check complete"
}

# Install Audio2Face Headless Server
install_audio2face_server() {
    print_step "Installing Audio2Face Headless Server..."

    # Create directories
    mkdir -p "$A2F_INSTALL_DIR"
    mkdir -p "$A2F_DATA_DIR"
    mkdir -p "$A2F_LOG_DIR"
    mkdir -p "$OMNIVERSE_CACHE"

    # Create service user
    if ! id "$SERVICE_USER" &>/dev/null; then
        useradd -r -s /bin/false -d "$A2F_DATA_DIR" "$SERVICE_USER"
        print_status "Created service user: $SERVICE_USER"
    fi

    # Option 1: Docker-based Audio2Face (Recommended)
    print_info "Setting up Docker-based Audio2Face server..."

    # Create Audio2Face Docker container
    cat > "$A2F_INSTALL_DIR/Dockerfile" << 'EOFDOCKER'
FROM nvcr.io/nvidia/omniverse/audio2face:2023.2.0

# Install headless dependencies
RUN apt-get update && apt-get install -y \
    python3-pip \
    python3-dev \
    libgomp1 \
    wget \
    && rm -rf /var/lib/apt/lists/*

# Install Python requirements
RUN pip3 install \
    flask \
    flask-cors \
    requests \
    numpy \
    pillow

# Create headless startup script
COPY start_headless.py /app/start_headless.py
COPY a2f_server.py /app/a2f_server.py

# Expose ports
EXPOSE 8011 8012

# Set environment
ENV ACCEPT_EULA=Y
ENV PRIVACY_CONSENT=Y
ENV A2F_HEADLESS=1
ENV CUDA_VISIBLE_DEVICES=0

WORKDIR /app

CMD ["python3", "/app/a2f_server.py"]
EOFDOCKER

    # Create Audio2Face headless server Python script
    cat > "$A2F_INSTALL_DIR/a2f_server.py" << 'EOFSERVER'
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
        'port': 8011
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
    logger.info("Starting Audio2Face Headless Server on port 8011")
    app.run(host='0.0.0.0', port=8011, debug=False)
EOFSERVER

    # Build Docker image
    print_info "Building Audio2Face Docker image..."
    cd "$A2F_INSTALL_DIR"

    # Note: This uses a placeholder image. In production, you'd need the actual NVIDIA Audio2Face image
    docker build -t audio2face-server:latest . 2>/dev/null || {
        print_warning "Cannot pull official Audio2Face image. Setting up mock server instead."
        setup_mock_server
        return
    }

    print_status "Audio2Face Docker image built"
}

# Setup mock server for testing
setup_mock_server() {
    print_info "Setting up mock Audio2Face server for testing..."

    cat > "$A2F_INSTALL_DIR/mock_a2f_server.py" << 'EOFMOCK'
#!/usr/bin/env python3
"""Mock Audio2Face server for testing when real A2F is not available"""

from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import tempfile
import subprocess
import os

app = Flask(__name__)
CORS(app)

@app.route('/status')
def status():
    return jsonify({
        'status': 'running',
        'mode': 'mock',
        'version': 'mock-1.0',
        'warning': 'This is a mock server for testing. Install real Audio2Face for production.'
    })

@app.route('/generate', methods=['POST'])
def generate():
    """Generate mock video for testing"""
    try:
        # Create a simple test video
        output = tempfile.NamedTemporaryFile(suffix='.mp4', delete=False).name

        # Generate test video with FFmpeg
        cmd = [
            'ffmpeg', '-f', 'lavfi', '-i', 'testsrc=duration=3:size=512x512:rate=30',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=3',
            '-c:v', 'libx264', '-c:a', 'aac', '-y', output
        ]

        subprocess.run(cmd, capture_output=True)

        if os.path.exists(output):
            return send_file(output, mimetype='video/mp4')
        else:
            return jsonify({'error': 'Mock generation failed'}), 500

    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/characters')
def characters():
    return jsonify({
        'characters': ['mock_character_1', 'mock_character_2'],
        'current': 'mock_character_1'
    })

if __name__ == '__main__':
    print("Starting Mock Audio2Face Server on port 8011")
    app.run(host='0.0.0.0', port=8011, debug=False)
EOFMOCK

    chmod +x "$A2F_INSTALL_DIR/mock_a2f_server.py"
}

# Create systemd service
create_systemd_service() {
    print_step "Creating systemd service..."

    cat > /etc/systemd/system/audio2face.service << EOFSERVICE
[Unit]
Description=NVIDIA Audio2Face Headless Server
After=network.target docker.service
Requires=docker.service

[Service]
Type=simple
User=root
Group=docker
WorkingDirectory=$A2F_INSTALL_DIR

# Docker-based service
ExecStartPre=/usr/bin/docker stop audio2face-server || true
ExecStartPre=/usr/bin/docker rm audio2face-server || true

ExecStart=/usr/bin/docker run --rm \\
    --name audio2face-server \\
    --gpus all \\
    -p ${A2F_PORT}:8011 \\
    -v ${A2F_DATA_DIR}:/data \\
    -v ${A2F_LOG_DIR}:/logs \\
    -e NVIDIA_VISIBLE_DEVICES=all \\
    -e NVIDIA_DRIVER_CAPABILITIES=all \\
    audio2face-server:latest

ExecStop=/usr/bin/docker stop audio2face-server

Restart=always
RestartSec=10

StandardOutput=append:${A2F_LOG_DIR}/audio2face.log
StandardError=append:${A2F_LOG_DIR}/audio2face.error.log

[Install]
WantedBy=multi-user.target
EOFSERVICE

    # Alternative: Python-based service for mock server
    cat > /etc/systemd/system/audio2face-mock.service << EOFMOCKSERVICE
[Unit]
Description=Mock Audio2Face Server (Testing)
After=network.target

[Service]
Type=simple
User=$SERVICE_USER
Group=$SERVICE_USER
WorkingDirectory=$A2F_INSTALL_DIR

ExecStart=/usr/bin/python3 $A2F_INSTALL_DIR/mock_a2f_server.py

Restart=always
RestartSec=10

StandardOutput=append:${A2F_LOG_DIR}/audio2face-mock.log
StandardError=append:${A2F_LOG_DIR}/audio2face-mock.error.log

[Install]
WantedBy=multi-user.target
EOFMOCKSERVICE

    print_status "Systemd services created"
}

# Configure firewall
configure_firewall() {
    print_step "Configuring firewall..."

    if command -v ufw &> /dev/null; then
        ufw allow $A2F_PORT/tcp
        print_status "Firewall rule added for port $A2F_PORT"
    fi
}

# Start services
start_services() {
    print_step "Starting Audio2Face services..."

    # Reload systemd
    systemctl daemon-reload

    # Try to start Docker-based service first
    if docker images | grep -q "audio2face-server"; then
        systemctl enable audio2face.service
        systemctl start audio2face.service

        # Check if started successfully
        sleep 5
        if systemctl is-active --quiet audio2face.service; then
            print_status "Audio2Face Docker service started"
            A2F_MODE="docker"
        else
            print_warning "Docker service failed, falling back to mock"
            systemctl stop audio2face.service
            systemctl disable audio2face.service

            systemctl enable audio2face-mock.service
            systemctl start audio2face-mock.service
            A2F_MODE="mock"
        fi
    else:
        # Start mock service
        systemctl enable audio2face-mock.service
        systemctl start audio2face-mock.service
        print_status "Mock Audio2Face service started"
        A2F_MODE="mock"
    fi
}

# Test installation
test_installation() {
    print_step "Testing Audio2Face server..."

    sleep 3

    # Test status endpoint
    if curl -s "http://localhost:${A2F_PORT}/status" > /dev/null 2>&1; then
        print_status "Audio2Face server responding on port $A2F_PORT"

        # Get detailed status
        STATUS=$(curl -s "http://localhost:${A2F_PORT}/status")
        echo "Server status: $STATUS"
    else
        print_error "Audio2Face server not responding"
        print_info "Check logs: journalctl -u audio2face -f"
    fi
}

# Create management script
create_management_script() {
    print_step "Creating management script..."

    cat > /usr/local/bin/audio2face-manager << 'EOFMANAGER'
#!/bin/bash

case "$1" in
    start)
        systemctl start audio2face
        ;;
    stop)
        systemctl stop audio2face
        ;;
    restart)
        systemctl restart audio2face
        ;;
    status)
        systemctl status audio2face
        curl -s http://localhost:8011/status | jq '.'
        ;;
    logs)
        journalctl -u audio2face -f
        ;;
    test)
        curl -X POST http://localhost:8011/generate \
            -F "audio=@test.wav" \
            -F "character=default" \
            --output test_output.mp4
        ;;
    *)
        echo "Usage: audio2face-manager {start|stop|restart|status|logs|test}"
        exit 1
        ;;
esac
EOFMANAGER

    chmod +x /usr/local/bin/audio2face-manager
    print_status "Management script created: audio2face-manager"
}

# Main installation flow
main() {
    check_prerequisites
    install_audio2face_server
    create_systemd_service
    configure_firewall
    start_services
    test_installation
    create_management_script

    # Set permissions
    chown -R ${SERVICE_USER}:${SERVICE_USER} "$A2F_DATA_DIR" "$A2F_LOG_DIR"
    chmod -R 755 "$A2F_INSTALL_DIR"

    echo ""
    echo "============================================"
    echo -e "${GREEN}   AUDIO2FACE SERVER INSTALLATION COMPLETE${NC}"
    echo "============================================"
    echo ""
    echo "📍 Server URL: http://localhost:${A2F_PORT}"
    echo "📁 Installation: $A2F_INSTALL_DIR"
    echo "📊 Mode: $A2F_MODE"
    echo ""
    echo "🎮 Management Commands:"
    echo "   audio2face-manager start    - Start server"
    echo "   audio2face-manager stop     - Stop server"
    echo "   audio2face-manager status   - Check status"
    echo "   audio2face-manager logs     - View logs"
    echo ""
    echo "🧪 Test the server:"
    echo "   curl http://localhost:${A2F_PORT}/status"
    echo ""

    if [ "$A2F_MODE" == "mock" ]; then
        print_warning "Running in MOCK mode (real Audio2Face not available)"
        print_info "To use real Audio2Face:"
        print_info "1. Install NVIDIA Omniverse Launcher"
        print_info "2. Install Audio2Face from Omniverse"
        print_info "3. Re-run this installer"
    fi
}

# Run main installation
main "$@"