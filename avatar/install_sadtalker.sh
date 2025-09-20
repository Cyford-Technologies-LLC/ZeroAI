#!/bin/bash

echo "Installing SadTalker..."

# Install system dependencies
apt-get update
apt-get install -y git wget unzip ca-certificates curl

# Download SadTalker release
cd /app
wget -O sadtalker.tar.gz https://github.com/OpenTalker/SadTalker/archive/refs/tags/v0.0.2-rc.tar.gz
tar -xzf sadtalker.tar.gz
mv SadTalker-0.0.2-rc SadTalker
cd SadTalker

# Install Python dependencies
pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cpu
pip install -r requirements.txt

# Download checkpoints using official script
echo "Running official SadTalker download script..."
bash scripts/download_models.sh

# Verify critical files exist and have content
echo "Verifying checkpoint files..."
ls -la checkpoints/

if [ -f checkpoints/SadTalker_V0.0.2_256.safetensors ] && [ -s checkpoints/SadTalker_V0.0.2_256.safetensors ]; then
    echo "✓ SadTalker_V0.0.2_256.safetensors downloaded successfully"
else
    echo "✗ SadTalker_V0.0.2_256.safetensors missing or empty"
fi

if [ -f checkpoints/mapping_00229-model.pth.tar ] && [ -s checkpoints/mapping_00229-model.pth.tar ]; then
    echo "✓ mapping_00229-model.pth.tar downloaded successfully"
else
    echo "✗ mapping_00229-model.pth.tar missing or empty"
fi

echo "SadTalker installation complete"