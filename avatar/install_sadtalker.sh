#!/bin/bash

echo "Installing SadTalker..."

# Install system dependencies
apt-get update
apt-get install -y git wget ffmpeg

# Clone official SadTalker repository
cd /app
git clone https://github.com/OpenTalker/SadTalker.git
cd SadTalker

# Install PyTorch with CUDA support
pip install torch==1.12.1+cu113 torchvision==0.13.1+cu113 torchaudio==0.12.1 --extra-index-url https://download.pytorch.org/whl/cu113

# Install SadTalker requirements
pip install -r requirements.txt

echo "✅ SadTalker installation complete"