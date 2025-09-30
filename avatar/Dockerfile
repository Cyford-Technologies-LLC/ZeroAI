FROM nvidia/cuda:12.1.0-base-ubuntu22.04
#FROM nvcr.io/nvidia/omniverse/audio2face:2023.2.0


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

# Install system dependencies (rarely change - cache friendly)
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3.10 \
    python3-pip \
    python3-dev \
    build-essential \
    cmake \
    libopenblas-dev \
    liblapack-dev \
    libx11-dev \
    libgtk-3-dev \
    cuda-toolkit-12-1 \
    libgl1-mesa-glx \
    libglib2.0-0 \
    libgstreamer1.0-0 \
    gstreamer1.0-plugins-base \
    gstreamer1.0-plugins-good \
    gstreamer1.0-plugins-bad \
    gstreamer1.0-plugins-ugly \
    gstreamer1.0-libav \
    libgomp1 \
    ffmpeg \
    git \
    wget \
    pax-utils \
    curl \
    htop \
    espeak  \
    espeak espeak-data \
    && rm -rf /var/lib/apt/lists/* \
    && ln -s /usr/bin/python3 /usr/bin/python

# Create directories and user early (rarely change - cache friendly)
RUN mkdir -p /app/faces \
    /tmp/gfpgan \
    /app/static \
    /app/scripts \
    /app/logs \
    /tmp/streaming_cache \
    /tmp/torch_cache \
    /tmp/facexlib_cache \
    tmp/gfpgan_cache \
    /tmp/huggingface_cache \
    /tmp/.cache \
    /home/avataruser/.config \
    /opt/audio2face \
    /var/lib/audio2face \
    /var/log/audio2face \
    && groupadd -r avataruser \
    && useradd -r -g avataruser avataruser \
    && chmod -R 777 /tmp/  /home/avataruser/\
    && chown -R avataruser:avataruser /tmp/ /home/avataruser/

# Copy only requirements first (change occasionally - cache friendly)
COPY requirements.txt .
# Create headless startup script
COPY start_headless.py /app/start_headless.py
COPY a2f_server.py /app/a2f_server.py


# Install Python packages (change occasionally - cache friendly)
RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r requirements.txt

# Test CUDA installation (rarely change - cache friendly)
RUN python3 -c "import torch; print(f'CUDA Available: {torch.cuda.is_available()}'); print(f'CUDA Device: {torch.cuda.get_device_name(0) if torch.cuda.is_available() else \"No CUDA Device\"}')"

# Fix PyTorch permissions early (rarely change - cache friendly)
RUN TORCH_PATH=$(python3 -c "import torch; import os; print(os.path.dirname(torch.__file__))" 2>/dev/null || echo "") \
    && if [ -n "$TORCH_PATH" ]; then \
        find "$TORCH_PATH" -name "*.so" -exec chmod 755 {} \; || true; \
    else \
        echo "Could not locate PyTorch installation, skipping permission fixes"; \
    fi

# Copy SadTalker installation script (changes occasionally - semi cache friendly)
COPY scripts/install_sadtalker.sh /app/install_sadtalker.sh

# Install SadTalker (large operation - cache this!)
RUN if [ -f "/app/install_sadtalker.sh" ]; then \
        chmod +x /app/install_sadtalker.sh \
        && bash /app/install_sadtalker.sh \
        && rm -rf /tmp/* \
        && rm /app/install_sadtalker.sh; \
    else \
        echo "SadTalker installation script not found, skipping..."; \
    fi

# Copy face creation script (changes occasionally)
COPY scripts/create_face.py /app/create_face.py

# Create default face (semi-stable operation)
RUN if [ -f "/app/create_face.py" ]; then \
        python3 create_face.py \
        && rm /app/create_face.py; \
    else \
        echo "create_face.py not found, skipping..."; \
    fi

# Copy static assets (change less frequently than code)
COPY faces/ /app/faces/

# Set ownership for static content
RUN chown -R avataruser:avataruser /app/faces /app/static /app/logs /tmp/ /home/avataruser     /opt/audio2face \
    /var/lib/audio2face \
    /var/log/audio2face \

# Copy application code (changes most frequently - put LAST)
COPY scripts/*.py /app/

# Final ownership and permissions (small operation)
RUN chown -R avataruser:avataruser /app/*.py \
    && chmod +x /app/*.py \
    && chmod -R 755 /app/

# Switch to non-root user
USER avataruser

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:7860/health || exit 1


RUN chown -R avataruser:avataruser /tmp/ /home/avataruser/


EXPOSE 7860 8011 8012
CMD ["python3", "avatar_api.py"]