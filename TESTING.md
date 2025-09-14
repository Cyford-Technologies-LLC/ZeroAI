# ZeroAI Testing

## Test Environment

Test containers have been moved to maintain separation between open-source and Cyford-specific resources.

**Location**: `knowledge/internal_crew/cyford/zeroai/resources/`

## Quick Start

```bash
# Navigate to test resources
cd knowledge/internal_crew/cyford/zeroai/resources

# Start test environment (CPU)
docker-compose -f docker-compose.testing.yml up -d

# Start test environment (GPU)
docker-compose -f docker-compose.testing.yml -f docker-compose.testing.gpu.yml up -d

# Stop test environment
docker-compose -f docker-compose.testing.yml down
```

## Test URLs

- **API**: http://localhost:4949
- **Web Interface**: http://localhost:444  
- **Peer Service**: http://localhost:9090
- **Ollama**: http://localhost:12434

## Features

- ✅ Redis caching enabled
- ✅ Background peer monitoring
- ✅ Cron job management
- ✅ Isolated test network
- ✅ GPU support available