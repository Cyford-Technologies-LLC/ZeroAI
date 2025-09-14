# Cyford ZeroAI Test Resources

This directory contains Cyford-specific test containers and resources for ZeroAI development.

## Test Containers

### Starting Test Environment

#### Quick Start (Windows)
```cmd
# From test resources directory
quick_test.bat
```

#### Interactive Menu
```cmd
# Windows
test_runner.bat

# Linux/Mac
./test_runner.sh
```

#### Manual Commands
```bash
# From ZeroAI root directory
cd knowledge/internal_crew/cyford/zeroai/resources

# CPU-only test environment
docker-compose -f docker-compose.testing.yml up -d

# GPU-enabled test environment
docker-compose -f docker-compose.testing.yml -f docker-compose.testing.gpu.yml up -d
```

### Test Ports

- **ZeroAI API Test**: http://localhost:4949
- **ZeroAI Web Test**: http://localhost:444
- **Peer Service Test**: http://localhost:9090
- **Ollama Test**: http://localhost:12434

### Features Included

- Redis caching for performance testing
- Background peer monitoring
- Cron job management
- Full web interface testing
- Isolated network for testing

### Stopping Test Environment

```bash
docker-compose -f docker-compose.testing.yml down
```

## Directory Structure

- `docker-compose.testing.yml` - Main test container configuration
- `docker-compose.testing.gpu.yml` - GPU support override
- `README.md` - This documentation

## Notes

- Test containers use different ports to avoid conflicts with production
- All volumes are mapped to the main ZeroAI directory structure
- Redis is automatically started in test containers
- Background services (peer monitoring, cron) are enabled by default