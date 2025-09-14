#!/bin/bash
# Start Redis in running container

docker exec -it zeroai_api-prod bash -c "
apt-get update && 
apt-get install -y redis-server && 
redis-server --daemonize yes &&
echo 'Redis started successfully'
"