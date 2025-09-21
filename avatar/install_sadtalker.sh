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

# Fix CUDA model loading issue for CPU-only systems
echo "Patching SadTalker for CPU-only operation..."

# Patch inference.py to force CPU device
sed -i 's/device = "cuda"/device = "cpu"/g' inference.py
sed -i 's/torch.cuda.is_available()/False/g' inference.py

# Patch model loading to use CPU mapping
find . -name "*.py" -exec sed -i 's/torch.load(/torch.load(/g; s/torch.load(\([^)]*\))/torch.load(\1, map_location="cpu")/g' {} \;

# Create CPU-compatible wrapper script
cat > inference_cpu.py << 'EOF'
import sys
import os
sys.path.insert(0, '/app/SadTalker')
os.environ['CUDA_VISIBLE_DEVICES'] = ''
import torch
torch.cuda.is_available = lambda: False
from inference import *
EOF

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

echo "SadTalker installation complete"