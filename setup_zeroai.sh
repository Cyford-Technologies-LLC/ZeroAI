#!/bin/bash
# ZeroAI Complete Setup Script
# Usage: ./setup_zeroai.sh [prod|test|both] [clean|prune] [--options=prune_all]

set -e

ENV=${1:-prod}
CLEAN=${2:-false}
OPTIONS=${3:-false}

# Check for prune_all option
if [[ "$*" == *"--options=prune_all"* ]]; then
    PRUNE_ALL=true
else
    PRUNE_ALL=false
fi

echo "🚀 Starting ZeroAI Setup for: $ENV..."
echo "Usage: ./setup_zeroai.sh [prod|test|both] [clean|prune] [--options=prune_all]"
echo "  clean - Remove ZeroAI containers and images"
echo "  prune - Full cleanup: remove all containers, images, and system prune"
echo "  --options=prune_all - Nuclear option: prune EVERYTHING before building"

# Create directory structure
echo "📁 Creating directory structure..."
sudo mkdir -p /opt/cyford/zeroai
sudo mkdir -p /etc/cyford/zeroai/{data,backup,knowledge,logs}
sudo chown -R $USER:$USER /opt/cyford
sudo chown -R $USER:$USER /etc/cyford

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "🐳 Docker not found. Running setup-docker.sh..."
    if [ -f "./setup-docker.sh" ]; then
        chmod +x ./setup-docker.sh
        ./setup-docker.sh
        echo "✅ Docker setup complete"
    else
        echo "❌ setup-docker.sh not found. Installing Docker manually..."
        curl -fsSL https://get.docker.com -o get-docker.sh
        sudo sh get-docker.sh
        sudo usermod -aG docker $USER
        rm get-docker.sh
    fi
else
    echo "✅ Docker already installed"
fi

# Fix permissions
chmod +x start_services.sh

# Docker Compose is now built into Docker
echo "✅ Using Docker Compose (built-in)"

# Copy files to persistent locations
echo "📋 Copying configuration files..."
if [ ! -f /etc/cyford/zeroai/.env ]; then
    cp .env.example /etc/cyford/zeroai/.env
    echo "📝 Edit /etc/cyford/zeroai/.env with your API keys"
fi

# Copy knowledge base only if destination is empty
echo "🧠 Copying knowledge base..."
if [ ! "$(ls -A /etc/cyford/zeroai/knowledge 2>/dev/null)" ]; then
    cp -r knowledge/* /etc/cyford/zeroai/knowledge/ 2>/dev/null || true
    echo "  ✅ Knowledge base copied"
else
    echo "  ⚠️ Knowledge base exists - skipping to preserve data"
fi


# Set proper permissions
echo "🔐 Setting permissions..."
sudo chown -R $USER:$USER /etc/cyford/zeroai
chmod -R 755 /etc/cyford/zeroai

# Prune all if requested (nuclear option)
if [ "$PRUNE_ALL" = true ]; then
    echo "💥 NUCLEAR OPTION: Pruning EVERYTHING..."
    docker compose -f Docker-compose.yml down --rmi all --volumes --remove-orphans 2>/dev/null || true
    docker compose -f docker-compose.testing.yml down --rmi all --volumes --remove-orphans 2>/dev/null || true
    docker stop $(docker ps -aq) 2>/dev/null || true
    docker rm $(docker ps -aq) 2>/dev/null || true
    docker rmi $(docker images -q) 2>/dev/null || true
    docker system prune -af --volumes
    echo "✅ Nuclear prune complete - everything destroyed"
# Clean Docker if requested
elif [ "$CLEAN" = "clean" ]; then
    echo "🧹 Stopping ZeroAI containers..."
    docker stop zeroai_api-prod zeroai_peer-prod zeroai_ollama zeroai_api-test zeroai_peer-test zeroai_ollama-test 2>/dev/null || true
    echo "🗑️ Removing ZeroAI containers..."
    docker rm zeroai_api-prod zeroai_peer-prod zeroai_ollama zeroai_api-test zeroai_peer-test zeroai_ollama-test 2>/dev/null || true
    echo "🗑️ Removing ZeroAI images..."
    docker images | grep zeroai | awk '{print $3}' | xargs -r docker rmi -f
    docker images | grep "<none>" | awk '{print $3}' | xargs -r docker rmi -f
    echo "✅ ZeroAI cleanup complete"
elif [ "$CLEAN" = "prune" ]; then
    echo "💥 FULL PRUNE: Stopping all ZeroAI containers..."
    docker compose -f Docker-compose.yml down --rmi all --volumes --remove-orphans 2>/dev/null || true
    docker compose -f docker-compose.testing.yml down --rmi all --volumes --remove-orphans 2>/dev/null || true
    echo "🗑️ Removing all ZeroAI images..."
    docker images | grep zeroai | awk '{print $3}' | xargs -r docker rmi -f
    docker images | grep "<none>" | awk '{print $3}' | xargs -r docker rmi -f
    echo "🧹 System-wide Docker prune..."
    docker system prune -af
    echo "✅ Full prune complete"
fi

# Use setup-docker.sh for container deployment
echo "🐳 Using setup-docker.sh for container deployment..."
if [ -f "./setup-docker.sh" ]; then
    chmod +x ./setup-docker.sh
    ./setup-docker.sh
    echo "🌐 Access points:"
    echo "  - Production API: http://localhost:3939"
    echo "  - Production Web: http://localhost:333"
else
    echo "❌ setup-docker.sh not found. Manual Docker setup required."
    exit 1
fi

# Wait for services
echo "⏳ Waiting for services to start..."
sleep 15

echo "✅ ZeroAI Setup Complete!"
echo "📁 App Directory: /opt/cyford/zeroai/"
echo "📁 Data: /etc/cyford/zeroai/data/"
echo "📁 Backups: /etc/cyford/zeroai/backup/"
echo "🔧 Edit /etc/cyford/zeroai/.env with your API keys"
echo ""
echo "💡 Directory Structure:"
echo "  - Apps: /opt/cyford/ (all Cyford applications)"
echo "  - Data: /etc/cyford/ (persistent data and configs)"
echo "💡 Options:"
echo "  - Clean rebuild: ./setup_zeroai.sh prod clean"
echo "  - Full prune: ./setup_zeroai.sh prod prune"
echo "  - Nuclear option: ./setup_zeroai.sh prod --options=prune_all"
echo "  - Check status: docker ps"
echo "  - View logs: docker compose logs -f"