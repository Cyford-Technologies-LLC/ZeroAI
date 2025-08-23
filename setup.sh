#!/bin/bash
# ZeroAI Local Setup Script
# Zero Cost. Zero Cloud. Zero Limits.

set -e

echo "🚀 ZeroAI Local Setup"
echo "====================="

# Check OS
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    OS="linux"
elif [[ "$OSTYPE" == "darwin"* ]]; then
    OS="macos"
else
    echo "❌ Unsupported OS: $OSTYPE"
    exit 1
fi

echo "🖥️  Detected OS: $OS"

# Check Python
if ! command -v python3 &> /dev/null; then
    echo "❌ Python3 not found. Please install Python 3.8+"
    exit 1
fi

PYTHON_VERSION=$(python3 -c 'import sys; print(".".join(map(str, sys.version_info[:2])))')
echo "🐍 Python version: $PYTHON_VERSION"

# Check pip
if ! command -v pip3 &> /dev/null; then
    echo "📦 Installing pip..."
    python3 -m ensurepip --upgrade
fi

# Install Ollama if not present
if ! command -v ollama &> /dev/null; then
    echo "🤖 Installing Ollama..."
    curl -fsSL https://ollama.ai/install.sh | sh
else
    echo "✅ Ollama already installed"
fi

# Install Python dependencies
echo "📦 Installing Python dependencies..."
pip3 install -r requirements.txt

# Setup environment file
if [ ! -f .env ]; then
    echo "⚙️  Creating .env file..."
    cp .env.example .env
    echo "📝 Please edit .env with your configuration"
else
    echo "✅ .env file exists"
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
echo ""
echo "🚀 Quick start:"
echo "   python3 run_example.py"
echo ""
echo "📚 Documentation:"
echo "   cat README.md"
echo ""
echo "⚙️  Configuration:"
echo "   nano .env"