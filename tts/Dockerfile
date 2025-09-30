#FROM python:3.10-slim
FROM nvidia/cuda:12.1.0-base-ubuntu22.04

# Set environment variables (rarely change - cache friendly)
ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    DEBIAN_FRONTEND=noninteractive \
    NUMBA_CACHE_DIR='/tmp/numba_cache' \
    MPLCONFIGDIR=/tmp/matplotlib \
    GFPGAN_CACHE_DIR=/tmp/gfpgan_cache \
    A2F_PORT="7860" \
    OMNIVERSE_CACHE="/var/cache/omniverse" \
    SERVICE_USER="avataruser" \
    ACCEPT_EULA=Y \
    PRIVACY_CONSENT=Y \
    A2F_HEADLESS=1 \
    CUDA_VISIBLE_DEVICES=0

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg \
    libsndfile1 \
    espeak-ng espeak-ng-data \
    cuda-toolkit-12-1 \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

RUN python -c "from TTS.api import TTS; TTS(model_name='tts_models/en/ljspeech/fast_pitch')" || echo "Model download failed, will download at runtime"

# Test CUDA installation (rarely change - cache friendly)
RUN python3 -c "import torch; print(f'CUDA Available: {torch.cuda.is_available()}'); print(f'CUDA Device: {torch.cuda.get_device_name(0) if torch.cuda.is_available() else \"No CUDA Device\"}')"


COPY app.py .

EXPOSE 5000

CMD ["python", "app.py"]
