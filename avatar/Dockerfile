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

# Clone SadTalker for realistic talking faces
RUN git clone https://github.com/OpenTalker/SadTalker.git /app/SadTalker
WORKDIR /app/SadTalker
RUN pip install -r requirements.txt

# Download SadTalker models
RUN mkdir -p checkpoints && \
    wget -O checkpoints/SadTalker_V002.tar https://github.com/OpenTalker/SadTalker/releases/download/v0.0.2-rc/SadTalker_V002.tar && \
    tar -xf checkpoints/SadTalker_V002.tar -C checkpoints/

WORKDIR /app
COPY avatar_api.py .
COPY create_face.py .
RUN python create_face.py

EXPOSE 7860

CMD ["python", "avatar_api.py"]