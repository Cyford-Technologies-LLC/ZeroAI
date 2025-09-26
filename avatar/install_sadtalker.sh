#!/bin/bash
set -e  # Exit on any error

echo "Installing SadTalker..."

# Clone official SadTalker repository
cd /app
git clone https://github.com/OpenTalker/SadTalker.git
cd SadTalker

# Install SadTalker requirements
pip install --no-cache-dir -r requirements.txt

bash scripts/download_models.sh




# Download GFPGAN weights with improved error handling
mkdir -p gfpgan/weights
download_weight() {
    local url=$1
    local output=$2
    wget -t 3 -O "$output" "$url" || {
        echo "Failed to download $url"
        return 1
    }
}

# Download weights
WEIGHTS_BASE_URL="https://github.com/xinntao/facexlib/releases/download"
GFPGAN_URL="https://github.com/TencentARC/GFPGAN/releases/download"

download_weight "${WEIGHTS_BASE_URL}/v0.1.0/alignment_WFLW_4HG.pth" "gfpgan/weights/alignment_WFLW_4HG.pth"
download_weight "${WEIGHTS_BASE_URL}/v0.1.0/detection_Resnet50_Final.pth" "gfpgan/weights/detection_Resnet50_Final.pth"
download_weight "${WEIGHTS_BASE_URL}/v0.2.2/parsing_parsenet.pth" "gfpgan/weights/parsing_parsenet.pth"
download_weight "${GFPGAN_URL}/v1.3.0/GFPGANv1.4.pth" "gfpgan/weights/GFPGANv1.4.pth"

# Create checkpoints symlinks
mkdir -p /app/checkpoints
ln -sf /app/SadTalker/checkpoints/* /app/checkpoints/ 2>/dev/null || true

echo "✅ SadTalker installation complete"