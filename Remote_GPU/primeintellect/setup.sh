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
ollama serve &
sleep 5

# Download model
echo "📥 Downloading AI model..."
ollama pull llama3.2:1b

# Install Python dependencies
echo "📦 Installing dependencies..."
pip install fastapi uvicorn requests

# Start GPU bridge
echo "🌉 Starting ZeroAI GPU Bridge..."
# Kill any existing processes
pkill -f "gpu_bridge.py" 2>/dev/null || true
pkill -f "uvicorn" 2>/dev/null || true
sleep 1

python gpu_bridge.py &
sleep 3

echo "✅ ZeroAI GPU Bridge is running on port 8080"
echo "🔗 Test: curl http://localhost:8080/health"
echo "🧪 Test GPU: curl -X POST http://localhost:8080/process -H 'Content-Type: application/json' -d '{\"task\": \"Hello GPU!\", \"model\": \"llama3.2:1b\"}'"
echo "📊 Monitor: curl http://localhost:8080/health"