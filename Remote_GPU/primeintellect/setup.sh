#!/bin/bash
# Setup ZeroAI GPU Bridge on Prime Intellect instance

echo "🚀 Setting up ZeroAI GPU Bridge..."

# Install Ollama if not present
if ! command -v ollama &> /dev/null; then
    echo "📦 Installing Ollama..."
    curl -fsSL https://ollama.ai/install.sh | sh
fi

# Start Ollama
echo "🔄 Starting Ollama..."
ollama serve --host 0.0.0.0 &
sleep 5

# Download model
echo "📥 Downloading AI model..."
ollama pull llama3.1:8b

# Install Python dependencies
echo "📦 Installing dependencies..."
pip install fastapi uvicorn requests

# Start GPU bridge
echo "🌉 Starting ZeroAI GPU Bridge..."
python gpu_bridge.py

echo "✅ ZeroAI GPU Bridge is running on port 8001"
echo "🔗 Test: curl http://localhost:8001/health"