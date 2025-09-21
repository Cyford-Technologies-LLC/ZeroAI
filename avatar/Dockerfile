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
#RUN pip install --no-cache-dir -r requirements.txt
RUN pip install  -r requirements.txt


# Install SadTalker
COPY install_sadtalker.sh .
RUN chmod +x install_sadtalker.sh && ./install_sadtalker.sh

# Create models directory
RUN mkdir -p /app/models

COPY avatar_api.py .
COPY create_face.py .
RUN python create_face.py

EXPOSE 7860

CMD ["python", "avatar_api.py"]