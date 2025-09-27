FROM nvidia/cuda:12.1.0-base-ubuntu22.04

# Set environment variables
ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    DEBIAN_FRONTEND=noninteractive \
    NUMBA_CACHE_DIR=/tmp/numba_cache

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
    libgstreamer1.0-0 \
    gstreamer1.0-plugins-base \
    gstreamer1.0-plugins-good \
    gstreamer1.0-plugins-bad \
    gstreamer1.0-plugins-ugly \
    gstreamer1.0-libav \
    ffmpeg \
    git \
    wget \
    pax-utils \
    curl \
    htop \
    && rm -rf /var/lib/apt/lists/*

    # Create necessary directories
RUN mkdir -p /app/faces \
    /app/static \
    /app/scripts \
    /app/logs \
    /tmp/streaming_cache


# Copy only requirements first (cache friendly)
COPY requirements.txt .

# Install Torch first (GPU build)
# Install Python packages
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir -r requirements.txt


RUN ln -s /usr/bin/python3 /usr/bin/python

RUN python3 -c "import torch; print(f'CUDA Available: {torch.cuda.is_available()}'); print(f'CUDA Device: {torch.cuda.get_device_name(0) if torch.cuda.is_available() else \"No CUDA Device\"}')"

# Copy application code (this is the only thing that should invalidate cache often)
COPY faces /app/faces
COPY scripts/*  /app/


# Install SadTalker
RUN if [ -f "/app/install_sadtalker.sh" ]; then \
        chmod +x /app/install_sadtalker.sh && \
        bash /app/install_sadtalker.sh; \
    else \
        echo "SadTalker installation script not found, skipping..."; \
    fi


# Create default face if script exists
RUN if [ -f "/app/create_face.py" ]; then \
        python3 create_face.py; \
    else \
        echo "create_face.py not found, skipping..."; \
    fi



# Fix PyTorch library permissions dynamically
RUN python3 -c "import torch; import os; torch_path = os.path.dirname(torch.__file__); print(f'Torch path: {torch_path}')" && \
    TORCH_PATH=$(python3 -c "import torch; import os; print(os.path.dirname(torch.__file__))" 2>/dev/null || echo "") && \
    if [ -n "$TORCH_PATH" ]; then \
        find "$TORCH_PATH" -name "*.so" -exec chmod 755 {} \; || true; \
    else \
        echo "Could not locate PyTorch installation, skipping permission fixes"; \
    fi


# Create non-root user for security
RUN groupadd -r avataruser && useradd -r -g avataruser avataruser && \
    chown -R avataruser:avataruser /app /tmp/streaming_cache



USER avataruser

# Set permissions for application files
RUN chmod +x /app/*.py && \
    chmod -R 755 /app/



# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:7860/health || exit 1

EXPOSE 7860
CMD ["python3", "avatar_api.py"]
