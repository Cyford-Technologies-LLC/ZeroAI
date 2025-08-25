#!/bin/bash
# ZeroAI Local Setup Script
# Zero Cost. Zero Cloud. Zero Limits.

set -e

echo "🚀 ZeroAI Local Setup"
echo "====================="

# Check OS and package manager
if [[ -f /etc/os-release ]]; then
    . /etc/os-release
    OS_NAME=$ID
elif [[ "$OSTYPE" == "linux-gnu"* ]]; then
    OS_NAME="linux"
elif [[ "$OSTYPE" == "darwin"* ]]; then
    OS_NAME="macos"
else
    echo "❌ Unsupported OS: $OSTYPE"
    exit 1
fi

echo "🖥️  Detected OS: $OS_NAME"

# Function to get the correct package manager
get_pkg_manager() {
    if [[ "$OS_NAME" == "ubuntu" || "$OS_NAME" == "debian" ]]; then
        echo "apt"
    elif [[ "$OS_NAME" == "rocky" || "$OS_NAME" == "centos" || "$OS_NAME" == "fedora" ]]; then
        echo "dnf"
    else
        echo "unknown"
    fi
}

PKG_MANAGER=$(get_pkg_manager)

# Check and install Python 3.11 and necessary dependencies
if ! command -v python3.11 &> /dev/null; then
    echo "📦 Installing Python 3.11 and dependencies..."
    if [[ "$PKG_MANAGER" == "apt" ]]; then
        sudo apt update && sudo apt install -y python3.11 python3.11-venv python3.11-distutils python3.11-dev
    elif [[ "$PKG_MANAGER" == "dnf" ]]; then
        sudo dnf config-manager --set-enabled crb || true  # Enable CRB on Rocky, ignore if not present
        sudo dnf install -y python3.11 python3.11-venv python3.11-devel
    else
        echo "❌ No supported package manager found to install Python 3.11."
        exit 1
    fi
fi

# Install Python dependencies using python3.11's pip
echo "📦 Installing Python dependencies..."
python3.11 -m pip install --upgrade pip
python3.11 -m pip install pysqlite3-binary -r requirements.txt

# Setup environment file
if [ ! -f .env ]; then
    echo "⚙️  Creating .env file..."
    cp .env.example .env
    echo "📝 Please edit .env with your configuration"
else
    echo "✅ .env file exists"
fi

# Install Ollama if not present
if ! command -v ollama &> /dev/null; then
    echo "🤖 Installing Ollama..."
    curl -fsSL https://ollama.ai/install.sh | sh
else
    echo "✅ Ollama already installed"
fi

# Start Ollama if not running
if ! pgrep -x "ollama" > /dev/null; then
    echo "🔄 Starting Ollama..."
    ollama serve &
    sleep 3
else
    echo "✅ Ollama is running"
fi

# Pull default model
echo "📥 Checking for default model..."
if ! ollama list | grep -q "llama3.1:8b"; then
    echo "📥 Downloading llama3.1:8b model..."
    ollama pull llama3.1:8b
else
    echo "✅ Default model available"
fi

echo ""
echo "🎉 ZeroAI setup complete!"
echo "You can now run:"
echo "   python3.11 run/internal/basic_crew.py"
echo ""
