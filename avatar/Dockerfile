FROM python:3.9-slim

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    ffmpeg \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

RUN git clone https://github.com/OpenTalker/SadTalker.git .

RUN pip install torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cpu
RUN pip install -r requirements.txt
RUN pip install flask-cors

COPY avatar_api.py .

EXPOSE 7860

CMD ["python", "avatar_api.py"]