cd /opt/cyford/ZeroAI/
git reset --hard
git pull
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml stop avatar
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml rm -f avatar
docker image prune -a
docker system prune --volumes
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml build --no-cache avatar
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml up -d avatar
#docker logs zeroai_avatar --follow













