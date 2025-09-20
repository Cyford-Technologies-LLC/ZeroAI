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

# Download main model files
echo "Downloading mapping models..."
wget -q https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/mapping_00109-model.pth.tar
wget -q https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/mapping_00229-model.pth.tar

# Download all required checkpoint files from HuggingFace
echo "Downloading checkpoint files..."
wget -q -O epoch_20.pth https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/epoch_20.pth
wget -q -O BFM_Fitting.pth https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/BFM_Fitting.pth
wget -q -O hub_module.pth https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/hub_module.pth
wget -q -O SadTalker_V0.0.2_256.safetensors https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/SadTalker_V0.0.2_256.safetensors
wget -q -O SadTalker_V0.0.2_512.safetensors https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/SadTalker_V0.0.2_512.safetensors

# Verify downloads
echo "Verifying checkpoint files..."
ls -la
echo "Checkpoint download complete"

echo "SadTalker installation complete"