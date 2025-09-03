# 🌟 24/7 AI Operations Guide

> **Run your ZeroAI workforce continuously with enterprise-grade reliability**

## 🎯 Overview

ZeroAI provides multiple deployment options for 24/7 AI operations, from simple API servers to fully distributed container orchestration. This guide covers all deployment scenarios for continuous AI agent availability.

## 🚀 Quick Start - 24/7 Deployment

### **Option 1: Docker Compose (Recommended)**
```bash
# Start all services in background
docker-compose up -d

# Services available:
# - API Server: http://localhost:3939
# - Peer Service: http://localhost:8080
# - Ollama: http://localhost:11434
```

### **Option 2: Manual Services**
```bash
# Terminal 1: Start API Server
python API/api.py

# Terminal 2: Start Persistent Daemon
python run/internal/persistent_crew_daemon.py

# Terminal 3: Start Ollama (if not using Docker)
ollama serve
```

## 🏗️ Architecture Components

### **1. API Server** (`API/api.py`)
- **FastAPI REST endpoint** for crew execution
- **Auto-restart capabilities** with Docker
- **Request logging and monitoring**
- **Multiple output formats** (JSON, Pydantic, Raw)

### **2. Persistent Crew Daemon** (`run/internal/persistent_crew_daemon.py`)
- **Keeps crews alive** 24/7
- **Automatic restart** on failure
- **Graceful shutdown** handling
- **Status monitoring** every 30 seconds

### **3. Distributed Router** (`src/distributed_router.py`)
- **Load balancing** across multiple nodes
- **Automatic failover** to backup systems
- **GPU provider management**
- **Peer discovery and health checks**

### **4. Container Orchestration**
- **Docker Compose** for service management
- **Auto-restart policies** on container failure
- **Volume persistence** for data continuity
- **Network isolation** and security

## 📡 API Endpoints

### **Execute AI Crew**
```bash
curl -X POST "http://localhost:3939/run_crew_ai_json/" \
  -H "Content-Type: application/json" \
  -d '{
    "inputs": {
      "topic": "analyze security vulnerabilities",
      "category": "security",
      "ai_provider": "ollama",
      "model_name": "llama3.1:8b"
    }
  }'
```

### **Response Format**
```json
{
  "final_result": "Security analysis complete...",
  "raw_output": "Detailed analysis...",
  "token_usage": {
    "total_tokens": 1500,
    "prompt_tokens": 800,
    "completion_tokens": 700
  },
  "tasks_output": [...]
}
```

## 🔧 Configuration

### **Environment Variables** (`.env`)
```bash
# Core Settings
OLLAMA_BASE_URL=http://localhost:11434
CREWAI_DISABLE_TELEMETRY=true

# API Configuration
API_HOST=0.0.0.0
API_PORT=3939

# Persistent Crews
PERSISTENT_CREWS_ENABLED=true
DEFAULT_PROJECTS=zeroai,testcorp

# GPU Providers (Optional)
RUNPOD_API_KEY=your_key_here
PRIMEINTELLECT_API_KEY=your_key_here
```

### **Docker Compose Configuration**
```yaml
# docker-compose.yml
services:
  zeroai:
    restart: unless-stopped
    environment:
      - PERSISTENT_CREWS_ENABLED=true
    volumes:
      - ./knowledge:/app/knowledge
      - /var/run/docker.sock:/var/run/docker.sock
    ports:
      - "3939:3939"
      - "8080:8080"
```

## 🛠️ Deployment Scenarios

### **1. Single Server Deployment**
```bash
# Clone and setup
git clone https://github.com/Cyford-Technologies-LLC/ZeroAI.git
cd ZeroAI

# Start 24/7 services
docker-compose up -d

# Verify services
curl http://localhost:3939/health
```

### **2. Multi-Node Cluster**
```bash
# Node 1 (Primary)
docker-compose up -d

# Node 2+ (Workers)
docker-compose -f docker-compose.worker.yml up -d

# Automatic peer discovery connects nodes
```

### **3. Cloud Deployment**
```bash
# AWS/GCP/Azure
docker-compose -f docker-compose.cloud.yml up -d

# With GPU support
docker-compose -f docker-compose.yml -f docker-compose.gpu.override.yml up -d
```

## 📊 Monitoring & Health Checks

### **Service Status**
```bash
# Check all services
docker-compose ps

# View logs
docker-compose logs -f zeroai

# Health check endpoint
curl http://localhost:3939/health
```

### **Crew Status**
```bash
# Check persistent crews
curl http://localhost:3939/crews/status

# Individual crew health
curl http://localhost:3939/crews/zeroai/health
```

### **Resource Monitoring**
```bash
# Container resources
docker stats

# System resources
htop

# GPU usage (if applicable)
nvidia-smi
```

## 🔒 Security & Best Practices

### **Production Security**
- **Reverse proxy** (Nginx/Traefik) for SSL termination
- **API authentication** and rate limiting
- **Network isolation** with Docker networks
- **Secret management** for API keys
- **Regular security updates**

### **Backup & Recovery**
```bash
# Backup knowledge base
tar -czf knowledge-backup.tar.gz knowledge/

# Backup configuration
cp .env .env.backup
cp docker-compose.yml docker-compose.backup.yml
```

## 🚨 Troubleshooting

### **Common Issues**

**Service Won't Start**
```bash
# Check logs
docker-compose logs zeroai

# Restart services
docker-compose restart

# Rebuild if needed
docker-compose up --build -d
```

**High Memory Usage**
```bash
# Monitor resources
docker stats

# Adjust memory limits in docker-compose.yml
```

**API Timeouts**
```bash
# Check Ollama connection
curl http://localhost:11434/api/tags

# Verify model availability
ollama list
```

### **Performance Optimization**

**GPU Acceleration**
```bash
# Enable GPU support
docker-compose -f docker-compose.gpu.override.yml up -d

# Verify GPU access
docker exec zeroai nvidia-smi
```

**Model Caching**
```bash
# Pre-pull models
ollama pull llama3.1:8b
ollama pull codellama:13b

# Verify cached models
ollama list
```

## 📈 Scaling & Load Balancing

### **Horizontal Scaling**
```bash
# Scale API servers
docker-compose up --scale zeroai=3 -d

# Load balancer configuration (Nginx)
upstream zeroai_backend {
    server localhost:3939;
    server localhost:3940;
    server localhost:3941;
}
```

### **Vertical Scaling**
```yaml
# Increase resources per container
services:
  zeroai:
    deploy:
      resources:
        limits:
          cpus: '4'
          memory: 8G
```

## 🔄 Maintenance & Updates

### **Rolling Updates**
```bash
# Pull latest image
docker-compose pull

# Rolling restart
docker-compose up -d --no-deps zeroai

# Verify update
curl http://localhost:3939/version
```

### **Scheduled Maintenance**
```bash
# Cron job for cleanup
0 2 * * * docker system prune -f

# Log rotation
0 0 * * * docker-compose logs --tail=1000 > logs/zeroai-$(date +%Y%m%d).log
```

## 🎯 Use Cases

### **Enterprise Development**
- **24/7 code review** and analysis
- **Automated testing** and deployment
- **Security vulnerability** scanning
- **Documentation** generation

### **Customer Support**
- **Always-on chatbots** for customer queries
- **Ticket routing** and prioritization
- **Knowledge base** maintenance
- **Multi-language support**

### **Business Intelligence**
- **Continuous market** analysis
- **Automated reporting** generation
- **Data pipeline** monitoring
- **Trend analysis** and alerts

### **DevOps Automation**
- **Infrastructure monitoring**
- **Automated incident** response
- **Deployment pipeline** management
- **Performance optimization**

## 📞 Support & Resources

- **Documentation**: https://docs.crewai.com/
- **GitHub Issues**: https://github.com/Cyford-Technologies-LLC/ZeroAI/issues
- **Community Discord**: [Join our community]
- **Enterprise Support**: Contact for 24/7 enterprise support

---

**ZeroAI: Your AI workforce never sleeps** 🌙✨