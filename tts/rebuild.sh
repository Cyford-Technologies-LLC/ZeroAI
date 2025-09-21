cd /opt/cyford/ZeroAI/
git reset --hard
git pull
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml stop tts-service
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml rm -f tts-service
docker image prune -a
docker system prune --volumes
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml build --no-cache tts-service
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml up -d tts-service
#docker logs zeroai_avatar --follow













