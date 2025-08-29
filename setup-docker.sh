#!/bin/bash

. setup/setup_docker.sh
docker compose -f docker-compose.yml -p zeroai-prod down


if lspci | grep -i 'NVIDIA' > /dev/null; then
    echo "NVIDIA GPU detected."
    # Add the NVIDIA package repository
    distribution=$(. /etc/os-release;echo $ID$VERSION_ID)
    curl -s -L https://nvidia.github.io/nvidia-docker/gpgkey | sudo gpg --dearmor -o /usr/share/keyrings/nvidia-docker-keyring.gpg
    curl -s -L https://nvidia.github.io/nvidia-docker/$distribution/nvidia-docker.list | sudo tee /etc/apt/sources.list.d/nvidia-docker.list

    sudo apt-get update
    sudo apt-get install -y nvidia-container-toolkit
    docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml -p zeroai-prod up --build -d
    docker compose -f docker-compose.learning.yml  -f docker-compose.gpu.override.yml -p zeroai-learning up --build -d
else
    echo "No NVIDIA GPU found."
    docker compose -f Docker-compose.yml -p zeroai-prod up --build -d
    docker compose -f docker-compose.learning.yml -p zeroai-learning up --build -d
fi

