FROM python:3.10-slim

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg \
    libsndfile1 \
    espeak-ng espeak-ng-data \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

RUN python -c "from TTS.api import TTS; TTS(model_name='tts_models/en/ljspeech/fast_pitch')" || echo "Model download failed, will download at runtime"


COPY app.py .

EXPOSE 5000

CMD ["python", "app.py"]
