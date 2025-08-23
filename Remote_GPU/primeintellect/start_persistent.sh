#!/bin/bash
# Start ZeroAI GPU Bridge persistently (survives logout)

echo "🚀 Starting persistent ZeroAI GPU Bridge..."

# Kill existing processes
pkill -f "ollama serve" 2>/dev/null || true
pkill -f "gpu_bridge.py" 2>/dev/null || true
sleep 2

# Start Ollama persistently
echo "🔄 Starting Ollama (persistent)..."
nohup ollama serve > ollama.log 2>&1 &
sleep 5

# Start GPU bridge persistently  
echo "🌉 Starting GPU Bridge (persistent)..."
nohup python gpu_bridge.py > gpu_bridge.log 2>&1 &
sleep 3

echo "✅ Services started persistently"
echo "📋 Check status: ps aux | grep -E '(ollama|gpu_bridge)'"
echo "📄 View logs: tail -f gpu_bridge.log"
echo "🔗 Test: curl http://localhost:8000/health"