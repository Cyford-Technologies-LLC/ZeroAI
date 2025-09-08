#!/bin/bash
"""Host-side setup script - run this on the Docker host, not inside containers."""

# Kill any process using port 333
sudo fuser -k 333/tcp 2>/dev/null || true

# Force stop and remove all containers
docker stop $(docker ps -aq) 2>/dev/null || true
docker rm $(docker ps -aq) 2>/dev/null || true
docker compose down --rmi all --remove-orphans --volumes
docker system prune --all --volumes --force

# Copy environment file
cp .env.example .env 2>/dev/null || true

# Get host user and group IDs
export LOCAL_UID=$(id -u)
export LOCAL_GID=$(id -g)

echo "Starting ZeroAI containers..."
echo "Host UID: $LOCAL_UID"
echo "Host GID: $LOCAL_GID"

# Start containers
env LOCAL_UID=$LOCAL_UID LOCAL_GID=$LOCAL_GID docker compose -f Docker-compose.yml -p zeroai-prod up --build -d

if [ $? -eq 0 ]; then
    echo "ZeroAI containers started successfully!"
    echo "Portal will be available at:"
    echo "  http://localhost:333"
    echo "  http://$(hostname -I | awk '{print $1}'):333"
else
    echo "Failed to start containers"
    exit 1
fi