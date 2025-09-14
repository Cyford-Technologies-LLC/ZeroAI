#!/bin/bash
echo "Starting ZeroAI Production Environment..."

# Stop any existing containers
docker compose down

# Build and start production
docker compose up --build -d

# Wait for services to start
sleep 10

# Check service status
echo "Checking service status..."
docker exec zeroai_api-prod redis-cli ping
docker exec zeroai_api-prod pgrep php-fpm
docker exec zeroai_api-prod curl -s http://localhost:333 > /dev/null && echo "Web server: OK" || echo "Web server: FAILED"

echo "Production environment started on:"
echo "- Web Portal: http://localhost:333"
echo "- API: http://localhost:3939"
echo "- Peer Service: http://localhost:8080"