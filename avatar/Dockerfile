FROM python:3.9-slim

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    ffmpeg \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libglib2.0-0 \
    cmake \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

# Copy our requirements first
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Clone SadTalker for avatar generation
RUN git clone https://github.com/OpenTalker/SadTalker.git sadtalker

# Copy our API
COPY avatar_api.py .

EXPOSE 7860

CMD ["python", "avatar_api.py"]