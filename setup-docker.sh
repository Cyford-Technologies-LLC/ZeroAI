#!/bin/sh

# --- Variables ---
# Get the directory where the script is located
SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
ENV_EXAMPLE="$SCRIPT_DIR/.env.example"
ENV_FILE="$SCRIPT_DIR/.env"

# --- Functions ---
# Log messages in a standard format
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

# Build and start the Docker containers with UID/GID passed as environment variables
log_info "Building and starting Docker containers with host permissions..."
# Use env to ensure the variables are set for the docker compose command
env LOCAL_UID=$HOST_UID LOCAL_GID=$HOST_GID docker compose up --build -d

# Check if docker compose succeeded
if [ $? -eq 0 ]; then
  log_info "ZeroAI setup complete. Containers are running."
else
  log_error "Docker Compose failed to start. Check the logs for details."
fi

exit 0
