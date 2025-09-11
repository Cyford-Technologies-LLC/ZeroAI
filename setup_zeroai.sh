#!/bin/bash
# ZeroAI Complete Setup Script
# Usage: ./setup_zeroai.sh [prod|test|both] [clean]

set -e

ENV=${1:-prod}
CLEAN=${2:-false}

echo "🚀 Starting ZeroAI Setup for: $ENV..."
echo "Usage: ./setup_zeroai.sh [prod|test|both] [clean]"
echo "  clean - Prune Docker system and remove ZeroAI images before building"

# Create directory structure
echo "📁 Creating directory structure..."
sudo mkdir -p /etc/cyford/zeroai/{data,backup,knowledge,logs}
sudo chown -R $USER:$USER /etc/cyford

# Install Docker if not present
if ! command -v docker &> /dev/null; then
    echo "🐳 Installing Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sudo sh get-docker.sh
    sudo usermod -aG docker $USER
    rm get-docker.sh
fi

# Docker Compose is now built into Docker
echo "✅ Using Docker Compose (built-in)"

# Copy files to persistent locations
echo "📋 Copying configuration files..."
if [ ! -f /etc/cyford/zeroai/.env ]; then
    cp .env.example /etc/cyford/zeroai/.env
    echo "📝 Edit /etc/cyford/zeroai/.env with your API keys"
fi

# Copy knowledge base
echo "🧠 Copying knowledge base..."
cp -r knowledge/* /etc/cyford/zeroai/knowledge/ 2>/dev/null || true

# Copy existing data if present
echo "💾 Copying existing data..."
if [ -f data/agents.db ]; then
    cp data/* /etc/cyford/zeroai/data/ 2>/dev/null || true
fi

# Set proper permissions
echo "🔐 Setting permissions..."
sudo chown -R $USER:$USER /etc/cyford/zeroai
chmod -R 755 /etc/cyford/zeroai

# Clean Docker if requested
if [ "$CLEAN" = "clean" ]; then
    echo "🧹 Stopping ZeroAI containers..."
    docker stop zeroai_api-prod zeroai_peer-prod zeroai_ollama zeroai_api-test zeroai_peer-test zeroai_ollama-test 2>/dev/null || true
    echo "🗑️ Removing ZeroAI containers..."
    docker rm zeroai_api-prod zeroai_peer-prod zeroai_ollama zeroai_api-test zeroai_peer-test zeroai_ollama-test 2>/dev/null || true
    echo "🗑️ Removing ZeroAI images..."
    docker images | grep zeroai | awk '{print $3}' | xargs -r docker rmi -f
    docker images | grep "<none>" | awk '{print $3}' | xargs -r docker rmi -f
    echo "✅ ZeroAI cleanup complete"
fi

# Build and start containers based on environment
case $ENV in
    "prod")
        echo "🏗️ Building production containers..."
        docker compose -f Docker-compose.yml build
        echo "🚀 Starting production ZeroAI..."
        docker compose -f Docker-compose.yml up -d
        echo "🌐 Production access: API http://localhost:3939, Web http://localhost:333"
        ;;
    "test")
        echo "🏗️ Building test containers..."
        docker compose -f docker-compose.testing.yml build
        echo "🚀 Starting test ZeroAI..."
        docker compose -f docker-compose.testing.yml up -d
        echo "🌐 Test access: API http://localhost:4949, Web http://localhost:444"
        ;;
    "both")
        echo "🏗️ Building all containers..."
        docker compose -f Docker-compose.yml build
        docker compose -f docker-compose.testing.yml build
        echo "🚀 Starting all ZeroAI services..."
        docker compose -f Docker-compose.yml up -d
        docker compose -f docker-compose.testing.yml up -d
        echo "🌐 Production: API http://localhost:3939, Web http://localhost:333"
        echo "🌐 Test: API http://localhost:4949, Web http://localhost:444"
        ;;
    *)
        echo "❌ Invalid environment. Use: prod, test, or both"
        exit 1
        ;;
esac

# Wait for services
echo "⏳ Waiting for services to start..."
sleep 15

echo "✅ ZeroAI Setup Complete!"
echo "📁 Data: /etc/cyford/zeroai/data/"
echo "📁 Backups: /etc/cyford/zeroai/backup/"
echo "🔧 Edit /etc/cyford/zeroai/.env with your API keys"
echo ""
echo "💡 Tips:"
echo "  - Use 'clean' option for fresh build: ./setup_zeroai.sh prod clean"
echo "  - Check status: docker ps"
echo "  - View logs: docker compose logs -f"