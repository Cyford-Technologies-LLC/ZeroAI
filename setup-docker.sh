#!/bin/bash

# --- Include the setup_docker.sh logic or define it here ---
# It's assumed your original setup script contains logic to set up Docker.
# If it just contains utility functions, you can include them here.
# For simplicity, this example assumes a minimal setup.

# --- Log messages in a standard format ---
log_info() {
  printf "\033[0;32m[INFO]\033[0m %s\n" "$1"
}

log_warn() {
  printf "\033[0;33m[WARN]\033[0m %s\n" "$1"
}

log_error() {
  printf "\033[0;31m[ERROR]\033[0m %s\n" "$1"
  exit 1
}

# --- Main Script ---
log_info "Starting ZeroAI setup..."

# Check for .env file and copy from .env.example if it doesn't exist
ENV_FILE="./.env"
ENV_EXAMPLE="./.env.example"
if [ ! -f "$ENV_FILE" ]; then
  if [ -f "$ENV_EXAMPLE" ]; then
    log_info "'.env' file not found. Copying from '.env.example'..."
    cp "$ENV_EXAMPLE" "$ENV_FILE"
  else
    log_error "'.env.example' file not found. Cannot proceed."
  fi
fi

# Get host user and group IDs and set environment variables
# These will be passed to docker compose for substitution
log_info "Retrieving host UID and GID..."
HOST_UID=$(id -u)
HOST_GID=$(id -g)
export LOCAL_UID=$HOST_UID
export LOCAL_GID=$HOST_GID

log_info "Host UID: $HOST_UID"
log_info "Host GID: $HOST_GID"

# Remove old containers to ensure a clean start
log_info "Removing old ZeroAI containers..."
docker compose -f Docker-compose.yml -p zeroai-prod down
# Remove old learning containers
docker compose -f docker-compose.learning.yml -p zeroai-learning down


# GPU detection and startup logic
if lspci | grep -i 'NVIDIA' > /dev/null; then
    log_info "NVIDIA GPU detected. Using GPU override configuration."
    # Add the NVIDIA package repository
    distribution=$(. /etc/os-release;echo $ID$VERSION_ID)
    curl -s -L https://nvidia.github.io/nvidia-docker/gpgkey | sudo gpg --dearmor -o /usr/share/keyrings/nvidia-docker-keyring.gpg
    curl -s -L https://nvidia.github.io/nvidia-docker/$distribution/nvidia-docker.list | sudo tee /etc/apt/sources.list.d/nvidia-docker.list

    sudo apt-get update
    sudo apt-get install -y nvidia-container-toolkit

    # Use env to ensure the variables are set for the docker compose command
    env LOCAL_UID=$HOST_UID LOCAL_GID=$HOST_GID docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml -p zeroai-prod up --build -d
    env LOCAL_UID=$HOST_UID LOCAL_GID=$HOST_GID docker compose -f docker-compose.learning.yml  -f docker-compose.gpu.override.yml -p zeroai-learning up --build -d
else
    log_info "No NVIDIA GPU found. Using standard configuration."
    # Use env to ensure the variables are set for the docker compose command
    env LOCAL_UID=$HOST_UID LOCAL_GID=$HOST_GID docker compose -f Docker-compose.yml -p zeroai-prod up --build -d
    env LOCAL_UID=$HOST_UID LOCAL_GID=$HOST_GID docker compose -f docker-compose.learning.yml -p zeroai-learning up --build -d
fi

# Check if docker compose succeeded
if [ $? -eq 0 ]; then
  log_info "ZeroAI setup complete. Containers are running."
else
  log_error "Docker Compose failed to start. Check the logs for details."
fi

exit 0
