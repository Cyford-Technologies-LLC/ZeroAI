FROM python:3.9-slim

WORKDIR /app

RUN apt-get update && apt-get install -y \
    ffmpeg \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libglib2.0-0 \
    libgomp1 \
    espeak \
    espeak-data \
    libespeak1 \
    libespeak-dev \
    git \
    wget \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Clone and setup SadTalker
RUN git clone https://github.com/OpenTalker/SadTalker.git /app/SadTalker && \
    cd /app/SadTalker && \
    pip install -r requirements.txt

# Create models directory and download checkpoints
RUN mkdir -p /app/models /app/checkpoints && \
    cd /app/checkpoints && \
    wget -O SadTalker_V002.safetensors https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/SadTalker_V002.safetensors && \
    wget -O mapping_00109-model.pth.tar https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/mapping_00109-model.pth.tar

COPY avatar_api.py .
COPY create_face.py .
RUN python create_face.py

EXPOSE 7860

CMD ["python", "avatar_api.py"]