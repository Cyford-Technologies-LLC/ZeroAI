#!/bin/bash

echo "Installing SadTalker..."

# Install system dependencies
apt-get update
apt-get install -y git wget unzip ca-certificates curl

# Clone official SadTalker repository
cd /app
git clone https://github.com/OpenTalker/SadTalker.git
cd SadTalker

# Use existing PyTorch installation (already installed via requirements.txt)
echo "Using existing PyTorch installation..."
python -c "import torch; print(f'PyTorch version: {torch.__version__}'); print(f'CUDA available: {torch.cuda.is_available()}')"

# Install additional SadTalker requirements (avoiding conflicts with existing packages)
echo "Installing additional SadTalker dependencies..."
pip install --no-deps yacs==0.1.8 face-alignment==1.3.5 imageio==2.19.3 imageio-ffmpeg==0.4.7 librosa==0.8.1 numba==0.56.4 resampy==0.2.2 pydub==0.25.1 scipy==1.10.1 tqdm==4.64.1 gfpgan basicsr facexlib

# Download models using official script
echo "Downloading SadTalker models..."
bash scripts/download_models.sh

# Verify installation
echo "Verifying SadTalker installation..."
ls -la checkpoints/
ls -la gfpgan/weights/

# Configure system priority settings
echo "Configuring system priority settings..."
echo 'python* - priority 10' >> /etc/security/limits.conf
echo 'root - priority 10' >> /etc/security/limits.conf

# Configure CPU limits
echo 'vm.swappiness=10' >> /etc/sysctl.conf
echo 'kernel.sched_rt_runtime_us=950000' >> /etc/sysctl.conf

# Create symlinks in /app/checkpoints/ where SadTalker actually looks for them
echo "Creating checkpoint symlinks in /app/checkpoints/..."
mkdir -p /app/checkpoints
cd /app/checkpoints
ln -sf /app/SadTalker/checkpoints/SadTalker_V0.0.2_256.safetensors epoch_20.pth
ln -sf /app/SadTalker/checkpoints/mapping_00229-model.pth.tar mapping_00229-model.pth.tar

echo "Verifying symlinks..."
ls -la /app/checkpoints/

echo "✅ SadTalker installation complete with all required files"