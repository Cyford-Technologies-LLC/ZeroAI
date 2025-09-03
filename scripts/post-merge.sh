#!/bin/bash
# Post-merge hook to fix imports automatically

echo "🔧 Running post-merge import fixes..."
python fix_imports.py

echo "🐳 Rebuilding Docker containers..."
docker compose -f Docker-compose.yml -p zeroai-prod down
docker compose -f Docker-compose.yml -p zeroai-prod up --build -d

echo "✅ Post-merge fixes complete!"