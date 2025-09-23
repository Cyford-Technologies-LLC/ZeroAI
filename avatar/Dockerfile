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


# Fix numpy compatibility issues in SadTalker
RUN cd /app/SadTalker && \
    sed -i 's/np\.float/np.float64/g' src/fa/face3d/util/my_awing_arch.py && \
    find . -name "*.py" -exec sed -i 's/np\.float/np.float64/g' {} \; && \
    find . -name "*.py" -exec sed -i 's/np\.int/np.int32/g' {} \;


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