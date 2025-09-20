#!/bin/bash
echo "Stopping avatar container..."
docker stop zeroai_avatar 2>/dev/null || true
docker rm zeroai_avatar 2>/dev/null || true

echo "Starting avatar container..."
docker-compose up -d avatar

echo "Waiting for avatar to start..."
sleep 10

echo "Checking avatar status..."
docker logs zeroai_avatar --tail 20