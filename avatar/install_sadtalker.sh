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

# Detect GPU and install appropriate PyTorch version
echo "Detecting GPU availability..."
if nvidia-smi > /dev/null 2>&1; then
    echo "✓ NVIDIA GPU detected - installing CUDA PyTorch"
    pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu118
else
    echo "✗ No NVIDIA GPU detected - installing CPU PyTorch"
    pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cpu
fi

# Install other requirements
pip install -r requirements.txt

# Create checkpoints directory
mkdir -p checkpoints

# Download checkpoints manually with direct URLs
echo "Downloading SadTalker checkpoints..."
cd checkpoints

# Download main SadTalker model
echo "Downloading SadTalker_V0.0.2_256.safetensors..."
wget -O SadTalker_V0.0.2_256.safetensors https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/SadTalker_V0.0.2_256.safetensors

# Download mapping file
echo "Downloading mapping_00229-model.pth.tar..."
wget -O mapping_00229-model.pth.tar https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/mapping_00229-model.pth.tar

# Download additional required files
echo "Downloading additional model files..."
wget -O BFM_Fitting.zip https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/BFM_Fitting.zip
unzip -o BFM_Fitting.zip

# Download GFPGAN weights
echo "Downloading GFPGAN weights..."
mkdir -p ../gfpgan/weights
cd ../gfpgan/weights
wget -O alignment_WFLW_4HG.pth https://github.com/xinntao/facexlib/releases/download/v0.1.0/alignment_WFLW_4HG.pth
wget -O detection_Resnet50_Final.pth https://github.com/xinntao/facexlib/releases/download/v0.1.0/detection_Resnet50_Final.pth
wget -O GFPGANv1.4.pth https://github.com/TencentARC/GFPGAN/releases/download/v1.3.0/GFPGANv1.4.pth
wget -O parsing_parsenet.pth https://github.com/xinntao/facexlib/releases/download/v0.2.2/parsing_parsenet.pth

cd /app/SadTalker

# Create universal CPU/GPU compatible wrapper
echo "Creating universal SadTalker wrapper..."
cat > inference_universal.py << 'EOF'
import sys
import os
import torch
sys.path.insert(0, '/app/SadTalker')

# Auto-detect device and patch model loading for CPU compatibility
if not torch.cuda.is_available() or os.environ.get('CUDA_VISIBLE_DEVICES') == '':
    print("Running in CPU mode - patching model loading")
    original_load = torch.load
    def cpu_load(f, map_location=None, **kwargs):
        if map_location is None:
            map_location = 'cpu'
        return original_load(f, map_location=map_location, **kwargs)
    torch.load = cpu_load

# Import and run original inference
from inference import *
EOF

# Verify critical files exist and have content
echo "Verifying checkpoint files..."
ls -la checkpoints/

if [ -f checkpoints/SadTalker_V0.0.2_256.safetensors ] && [ -s checkpoints/SadTalker_V0.0.2_256.safetensors ]; then
    echo "✓ SadTalker_V0.0.2_256.safetensors downloaded successfully ($(stat -c%s checkpoints/SadTalker_V0.0.2_256.safetensors) bytes)"
else
    echo "✗ SadTalker_V0.0.2_256.safetensors missing or empty"
    exit 1
fi

if [ -f checkpoints/mapping_00229-model.pth.tar ] && [ -s checkpoints/mapping_00229-model.pth.tar ]; then
    echo "✓ mapping_00229-model.pth.tar downloaded successfully ($(stat -c%s checkpoints/mapping_00229-model.pth.tar) bytes)"
else
    echo "✗ mapping_00229-model.pth.tar missing or empty"
    exit 1
fi

# Verify GFPGAN weights
if [ -f gfpgan/weights/GFPGANv1.4.pth ] && [ -s gfpgan/weights/GFPGANv1.4.pth ]; then
    echo "✓ GFPGAN weights downloaded successfully"
else
    echo "✗ GFPGAN weights missing or empty"
    exit 1
fi

# Configure system to run Python at lower priority
echo "Configuring system priority settings..."

# Set default nice level for Python processes
echo 'python* - priority 10' >> /etc/security/limits.conf
echo 'root - priority 10' >> /etc/security/limits.conf

# Create wrapper script for Python with nice and ionice priority
cat > /usr/local/bin/python-nice << 'EOF'
#!/bin/bash
nice -n 10 ionice -c 3 python "$@"
EOF
chmod +x /usr/local/bin/python-nice

# Configure CPU limits for intensive processes
echo 'vm.swappiness=10' >> /etc/sysctl.conf
echo 'kernel.sched_rt_runtime_us=950000' >> /etc/sysctl.conf

echo "✅ SadTalker installation complete with all required files"