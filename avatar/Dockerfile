FROM python:3.10-slim

WORKDIR /app

# Install base system dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg libsm6 libxext6 libxrender-dev libglib2.0-0 libgomp1 \
    espeak espeak-data libespeak1 git patchelf \
    && rm -rf /var/lib/apt/lists/*

# Copy only requirements first (cache friendly)
COPY requirements.txt .

# Install Torch first (GPU build)
#RUN pip install  --no-cache-dir -r requirements.txt


# Copy application code (this is the only thing that should invalidate cache often)
COPY avatar_api.py .
COPY create_face.py .
COPY faces /app/faces
COPY install_sadtalker.sh .


RUN bash install_sadtalker.sh
RUN python create_face.py

EXPOSE 7860
CMD ["python", "avatar_api.py"]
