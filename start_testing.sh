#!/bin/bash
echo "Starting ZeroAI Testing Environment..."

# Stop any existing test containers
docker compose -f docker-compose.test.yml down

# Build and start testing
docker compose -f docker-compose.test.yml up --build -d

# Wait for services to start
sleep 10

# Check service status
echo "Checking service status..."
docker exec zeroai_api-test redis-cli ping
docker exec zeroai_api-test pgrep php-fpm
docker exec zeroai_api-test curl -s http://localhost:333 > /dev/null && echo "Web server: OK" || echo "Web server: FAILED"

echo "Testing environment started on:"
echo "- Web Portal: http://localhost:334"
echo "- API: http://localhost:3940"
echo "- Peer Service: http://localhost:8081"