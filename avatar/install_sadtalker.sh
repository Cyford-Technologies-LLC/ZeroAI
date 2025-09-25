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
pip install torch==1.12.1+cu113 torchvision==0.13.1+cu113 torchaudio==0.12.1 \
    --extra-index-url https://download.pytorch.org/whl/cu113 || { echo "Failed to install PyTorch"; exit 1; }

# Install SadTalker requirements
pip install --no-cache-dir -r requirements.txt

# Download GFPGAN weights for face enhancement
echo "Downloading GFPGAN weights..."
mkdir -p gfpgan/weights
wget -O gfpgan/weights/alignment_WFLW_4HG.pth https://github.com/xinntao/facexlib/releases/download/v0.1.0/alignment_WFLW_4HG.pth
wget -O gfpgan/weights/detection_Resnet50_Final.pth https://github.com/xinntao/facexlib/releases/download/v0.1.0/detection_Resnet50_Final.pth
wget -O gfpgan/weights/parsing_parsenet.pth https://github.com/xinntao/facexlib/releases/download/v0.2.2/parsing_parsenet.pth
wget -O gfpgan/weights/GFPGANv1.4.pth https://github.com/TencentARC/GFPGAN/releases/download/v1.3.0/GFPGANv1.4.pth

# Create symlinks for API compatibility
echo "Creating checkpoint symlinks..."
mkdir -p /app/checkpoints
ln -sf /app/SadTalker/checkpoints/* /app/checkpoints/ 2>/dev/null || true

echo "✅ SadTalker installation complete"