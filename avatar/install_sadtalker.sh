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

# Download main model files from GitHub releases
echo "Downloading mapping models..."
wget -q https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/mapping_00109-model.pth.tar
wget -q https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/mapping_00229-model.pth.tar

# Download checkpoint files from working sources
echo "Downloading checkpoint files..."

# Try multiple sources for epoch_20.pth
wget -q -O epoch_20.pth https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/epoch_20.pth || \
wget -q -O epoch_20.pth https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/epoch_20.pth || \
wget -q -O epoch_20.pth https://drive.google.com/uc?id=1tF4DlFw2OjzBtW6zjp-EVbzZl-hy_kcw || \
echo "Failed to download epoch_20.pth from all sources"

# Download other required files
wget -q -O BFM_Fitting.pth https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/BFM_Fitting.pth || \
wget -q -O BFM_Fitting.pth https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/BFM_Fitting.pth || \
echo "BFM_Fitting.pth download failed"

wget -q -O hub_module.pth https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/hub_module.pth || \
wget -q -O hub_module.pth https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/hub_module.pth || \
echo "hub_module.pth download failed"

wget -q -O SadTalker_V0.0.2_256.safetensors https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/SadTalker_V0.0.2_256.safetensors || \
wget -q -O SadTalker_V0.0.2_256.safetensors https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/SadTalker_V0.0.2_256.safetensors || \
echo "SadTalker_V0.0.2_256.safetensors download failed"

wget -q -O SadTalker_V0.0.2_512.safetensors https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/SadTalker_V0.0.2_512.safetensors || \
wget -q -O SadTalker_V0.0.2_512.safetensors https://huggingface.co/vinthony/SadTalker/resolve/main/checkpoints/SadTalker_V0.0.2_512.safetensors || \
echo "SadTalker_V0.0.2_512.safetensors download failed"

# Verify critical files exist and have content
echo "Verifying checkpoint files..."
ls -la

if [ -f epoch_20.pth ] && [ -s epoch_20.pth ]; then
    echo "✓ epoch_20.pth downloaded successfully ($(stat -c%s epoch_20.pth) bytes)"
else
    echo "✗ epoch_20.pth missing or empty - SadTalker will not work"
fi

if [ -f BFM_Fitting.pth ] && [ -s BFM_Fitting.pth ]; then
    echo "✓ BFM_Fitting.pth downloaded successfully"
else
    echo "✗ BFM_Fitting.pth missing or empty"
fi

echo "SadTalker installation complete"