#!/bin/bash

echo "Installing SadTalker..."

# Install system dependencies
apt-get update
apt-get install -y git wget unzip ca-certificates curl

# Clone official SadTalker repository
cd /app
git clone https://github.com/OpenTalker/SadTalker.git
cd SadTalker

# Install SadTalker requirements (their exact requirements.txt)
#pip install -r requirements.txt

# Download models - use wget with direct URLs since their script may fail in Docker
echo "Downloading SadTalker models..."
mkdir -p checkpoints gfpgan/weights

# Download main models
#wget -O checkpoints/SadTalker_V0.0.2_256.safetensors https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/SadTalker_V0.0.2_256.safetensors
#wget -O checkpoints/mapping_00229-model.pth.tar https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/mapping_00229-model.pth.tar
#wget -O checkpoints/BFM_Fitting.zip https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/BFM_Fitting.zip
#wget -O checkpoints/hub.zip https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/hub.zip

# Extract archives
unzip -o checkpoints/BFM_Fitting.zip -d checkpoints/
unzip -o checkpoints/hub.zip -d checkpoints/

# Download GFPGAN weights
wget -O gfpgan/weights/alignment_WFLW_4HG.pth https://github.com/xinntao/facexlib/releases/download/v0.1.0/alignment_WFLW_4HG.pth
wget -O gfpgan/weights/detection_Resnet50_Final.pth https://github.com/xinntao/facexlib/releases/download/v0.1.0/detection_Resnet50_Final.pth
wget -O gfpgan/weights/parsing_parsenet.pth https://github.com/xinntao/facexlib/releases/download/v0.2.2/parsing_parsenet.pth
wget -O gfpgan/weights/GFPGANv1.4.pth https://github.com/TencentARC/GFPGAN/releases/download/v1.3.0/GFPGANv1.4.pth

# Verify critical files downloaded
echo "Verifying downloads..."
ls -la checkpoints/
ls -la gfpgan/weights/

# Create symlinks in /app/checkpoints/ where the API looks for them
echo "Creating checkpoint symlinks..."
mkdir -p /app/checkpoints
ln -sf /app/SadTalker/checkpoints/* /app/checkpoints/

# Configure system priority
echo 'python* - priority 10' >> /etc/security/limits.conf
echo 'root - priority 10' >> /etc/security/limits.conf

echo "✅ SadTalker installation complete"