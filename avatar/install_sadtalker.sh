#!/bin/bash
set -e

echo "Installing SadTalker..."

# Install system dependencies
apt-get update
apt-get install -y git wget unzip

# Clone SadTalker
cd /app
git clone https://github.com/OpenTalker/SadTalker.git
cd SadTalker

# Install Python dependencies
pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cpu
pip install -r requirements.txt

# Download checkpoints
mkdir -p checkpoints
cd checkpoints
wget https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/mapping_00109-model.pth.tar
wget https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/mapping_00229-model.pth.tar

# Download epoch_20.pth from alternative source
wget -O epoch_20.pth https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/epoch_20.pth || echo "Failed to download epoch_20.pth"

echo "SadTalker installation complete"