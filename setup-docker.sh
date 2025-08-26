#!/bin/bash

. setup/setup_docker.sh
docker compose -f docker-compose.yml -p zeroai-prod down
docker compose -f docker-compose.yml -p zeroai-prod up --build -d
docker compose -f docker-compose.learning.yml -p zeroai-learning up --build -d
