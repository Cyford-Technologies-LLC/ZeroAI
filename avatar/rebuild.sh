#!/bin/bash
set -e

cd /opt/cyford/ZeroAI/

NUKE=false
BUILD=false
if [[ "$1" == "--nuke" ]]; then
    NUKE=true
    echo "🔥 Full nuke mode enabled. Pruning and rebuilding from scratch..."
elif [[ "$1" == "--build" ]]; then
    BUILD=true
    echo "🔨 Normal build mode enabled. Reusing cache..."

else
    echo "🔄 Simple update mode. Using cache for rebuild..."
fi

# Reset codebase and pull latest
git reset --hard
git pull

# Stop and remove running container
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml stop avatar

if $NUKE; then
    # Nuke = remove all images/volumes and rebuild without cache
    docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml rm -f -s avatar
    docker builder prune -af
    docker image prune -af
    docker system prune -af --volumes

    docker volume prune -f
    docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml build --no-cache avatar

elif $BUILD; then
          # Normal = reuse cache, much faster
    docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml rm -f -s avatar
    docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml build avatar
else
    #  Simple restart
    docker cp avatar/avatar_api.py zeroai_avatar:/app/avatar_api.py
    docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml restart avatar

fi

# Start it back up
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml up -d avatar

echo "✅ Avatar container rebuilt and started."
echo "Tip: run './rebuild.sh --nuke' if you need a full clean rebuild."













