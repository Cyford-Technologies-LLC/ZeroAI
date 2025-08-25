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

# Check Python 3.11 and install if not present
if ! command -v python3.11 &> /dev/null; then
    echo "📦 Installing Python 3.11 and dependencies..."
    if [[ "$PKG_MANAGER" == "apt" ]]; then
        sudo apt update && sudo apt install -y python3.11 python3.11-venv python3.11-distutils python3.11-dev
    elif [[ "$PKG_MANAGER" == "dnf" ]]; then
        sudo dnf install -y python3.11 python3.11-venv python3.11-devel
    else
        echo "❌ No supported package manager found to install Python 3.11."
        exit 1
    fi
fi

# Create and activate virtual environment
if [ ! -d "venv-crewai" ]; then
    echo "🐍 Creating virtual environment..."
    python3.11 -m venv venv-crewai
fi
source venv-crewai/bin/activate

# Add pysqlite3-binary to requirements for compatibility
echo "pysqlite3-binary" >> requirements.txt

# Install Python dependencies
echo "📦 Installing Python dependencies..."
pip install -r requirements.txt

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
echo "🎉 ZeroAI setup complete! Virtual environment is active."
echo ""
echo "🚀 Quick start:"
echo "   python3 run/internal/basic_crew.py"
echo ""
echo "📚 Documentation:"
echo "   cat README.md"
echo ""
echo "⚙️  Configuration:"
echo "   nano .env"
