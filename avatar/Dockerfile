FROM python:3.10-slim

WORKDIR /app

# Install base system dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg libsm6 libxext6 libxrender-dev libglib2.0-0 libgomp1 \
    espeak espeak-data libespeak1 git  patchelf \
    && rm -rf /var/lib/apt/lists/*




# Copy and run SadTalker installation script
COPY install_sadtalker.sh .
COPY faces /app/faces
RUN chmod +x install_sadtalker.sh && ./install_sadtalker.sh

# Download SadTalker models during build
RUN cd /app/SadTalker && bash scripts/download_models.sh  || true

# Clear executable stack for libtorch_cpu.so
RUN find /usr/local/lib/python3.10/site-packages/torch -name "libtorch_cpu.so" -exec patchelf --clear-execstack {} \; || echo "PyTorch not found, skipping patchelf"


RUN cd /app/SadTalker && \
    find . -name "*.py" -exec sed -i 's/\bnp\.float\b/float/g' {} \; && \
    find . -name "*.py" -exec sed -i 's/\bnp\.int\b/int/g' {} \;

RUN sed -i 's/trans_params = np.array(\[w0, h0, s, t\[0\], t\[1\]\])/t = np.squeeze(t)\n    trans_params = np.array([w0, h0, s, float(t[0]), float(t[1])])/g' \
    /app/SadTalker/src/face3d/util/preprocess.py

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