#!/bin/bash
set -e

cd /opt/cyford/ZeroAI/

NUKE=false
if [[ "$1" == "--nuke" ]]; then
    NUKE=true
    echo "🔥 Full nuke mode enabled. Pruning and rebuilding from scratch..."
else
    echo "🔄 Simple update mode. Using cache for rebuild..."
fi

# Reset codebase and pull latest
git reset --hard
git pull

# Stop and remove running container
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml stop avatar
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml rm -f -s avatar

if $NUKE; then
    # Nuke = remove all images/volumes and rebuild without cache
    docker image prune -af
    docker system prune -af --volumes
    docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml build --no-cache avatar
else
    # Normal = reuse cache, much faster
    docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml build avatar
fi

# Start it back up
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml up -d avatar

echo "✅ Avatar container rebuilt and started."
echo "Tip: run './rebuild.sh --nuke' if you need a full clean rebuild."













