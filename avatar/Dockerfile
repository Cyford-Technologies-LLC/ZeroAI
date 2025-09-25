FROM nvidia/cuda:12.1.0-base-ubuntu22.04

# Set environment variables
ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    DEBIAN_FRONTEND=noninteractive

WORKDIR /app







# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3.10 \
    python3-pip \
    python3-dev \
    build-essential \
    cuda-toolkit-12-1 \
    libgl1-mesa-glx \
    libglib2.0-0 \
    ffmpeg \
    git \
    wget \
    pax-utils \
    && rm -rf /var/lib/apt/lists/*

# Copy only requirements first (cache friendly)
COPY requirements.txt .

# Install Torch first (GPU build)
RUN pip install  --no-cache-dir -r requirements.txt

# Install PyTorch with CUDA support
RUN pip3 install torch torchvision torchaudio \
    --index-url https://download.pytorch.org/whl/cu121

# Fix PyTorch library permissions
RUN find /usr/local/lib/python3.10/site-packages/torch -name "libtorch_cpu.so" -exec bash -c '\
    echo "Fixing permissions for {}"; \
    chmod 755 "{}"; \
    paxctl -cm "{}"; \
    ' \;

RUN python3 -c "import torch; print(f'CUDA Available: {torch.cuda.is_available()}'); print(f'CUDA Device: {torch.cuda.get_device_name(0) if torch.cuda.is_available() else \"No CUDA Device\"}')"

# Copy application code (this is the only thing that should invalidate cache often)
COPY avatar_api.py .
COPY create_face.py .
COPY faces /app/faces
COPY install_sadtalker.sh .


RUN bash install_sadtalker.sh
RUN python create_face.py
# Fix PyTorch library permissions
RUN if [ -f "/usr/local/lib/python3.10/site-packages/torch/lib/libtorch_cpu.so" ]; then \
    chmod 755 /usr/local/lib/python3.10/site-packages/torch/lib/libtorch_cpu.so && \
    paxctl -cm /usr/local/lib/python3.10/site-packages/torch/lib/libtorch_cpu.so; \
    fi
EXPOSE 7860
CMD ["python", "avatar_api.py"]
