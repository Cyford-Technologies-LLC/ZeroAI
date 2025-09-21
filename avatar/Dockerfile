FROM python:3.10-slim

WORKDIR /app

# Install base system dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg libsm6 libxext6 libxrender-dev libglib2.0-0 libgomp1 \
    espeak espeak-data libespeak1 git


# Copy and run SadTalker installation script
COPY install_sadtalker.sh .
RUN chmod +x install_sadtalker.sh && ./install_sadtalker.sh

# Copy your application requirements file
COPY requirements.txt .

# Install your API's specific Python dependencies
RUN pip install --no-cache-dir -r requirements.txt

# Copy your Python application code
COPY avatar_api.py .

# Copy other necessary files
COPY create_face.py .
RUN python create_face.py

EXPOSE 7860

CMD ["python", "avatar_api.py"]