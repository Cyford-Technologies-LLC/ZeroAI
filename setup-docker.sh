#!/bin/bash

. setup/setup_docker.sh
docker compose -f docker-compose.yml -p zeroai-prod down


if lspci | grep -i 'NVIDIA' > /dev/null; then
    echo "NVIDIA GPU detected."
    docker compose -f docker-compose.yml -f docker-compose.gpu.override.yml -p zeroai-prod up --build -d
    docker compose -f docker-compose.learning.yml  -f docker-compose.gpu.override.yml -p zeroai-learning up --build -d
else
    echo "No NVIDIA GPU found."
    docker compose -f docker-compose.yml -p zeroai-prod up --build -d
    docker compose -f docker-compose.learning.yml -p zeroai-learning up --build -d
fi

