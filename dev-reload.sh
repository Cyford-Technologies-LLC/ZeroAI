#!/bin/bash
# Development reload script - restart only the API service without full rebuild

echo "Restarting ZeroAI API service..."
docker-compose restart zeroai

echo "API service restarted. Portal changes should be live."
echo "Access: http://localhost:333"