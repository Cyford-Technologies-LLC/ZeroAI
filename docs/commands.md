# ZeroAI Commands Reference

## Setup Commands

### Download Models
```bash
# Download recommended models
for model in llama3.2:latest mixtral-8x7b-instruct-v0.1 gemma2:2b codellama:7b llava:7b; do
  ollama pull $model
done
```

### Peer Management
```bash
# Add GPU peer
python3 run/internal/peer_manager.py --ip 149.36.1.65 --name GPU-01 --model codellama:13b add

# List peers
python3 run/internal/peer_manager.py list
```

## Public API Commands

### REST API Endpoints
```bash
# Linux/Mac curl test
curl -X POST "http://localhost:3939/run_crew_ai_json/" \
  -H "Content-Type: application/json" \
  -d '{ "inputs": { "topic": "what is your name", "context": "general", "focus": "standard" } }'

# Windows PowerShell test
$body = @{ inputs = @{ topic = "what is your name"; context = "general"; focus = "standard" } } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://localhost:3939/run_crew_ai_json/" -ContentType "application/json" -Body $body
```

## CLI Direct Commands

### Basic Crew Operations
```bash
# Simple chat interface
clear ; python3 run/internal/simple_chat.py

# API crew test
clear ; python3 run/internal/api_crew.py

# Smart AI demo
clear ; python3 run/internal/smart_ai_demo.py

# Code generator
clear ; python3 run/internal/code_generator.py

# Basic crew example
clear ; python3 run/internal/basic_crew.py

# Advanced analysis
clear ; python3 run/examples/advanced_analysis.py
```

## Internal DevOps Commands

### Basic Usage
```bash
# Simple task
python run/internal/run_dev_ops.py "Fix a simple bug in the login form validation"

# With category specification
python run/internal/run_dev_ops.py --category=developer "Fix a simple bug in the login form validation"

# With project and repository
python run/internal/run_dev_ops.py --project=zeroai --repo=https://github.com/Cyford-Technologies-LLC/ZeroAI.git "Add error handling to the peer discovery system"

# Dry run for testing
python run/internal/run_dev_ops.py --dry-run "Test task that won't make changes"

# Verbose output
python run/internal/run_dev_ops.py -v "Task with detailed logging"
```

### Complex Examples
```bash
# Bug fix with repository operations
python run/internal/run_dev_ops.py "Fix a bug in the code where user login fails for repo https://github.com/myuser/my-test-app.git, update the README to reflect the change, and push the changes to a new branch 'fix-login'."

# Category-specific development task
cd /opt/ZeroAI
python run/internal/run_dev_ops.py --category=developer "Fix a simple bug in the login form validation"
```

## Learning System Commands

### Analysis Commands
```bash
# View learning summary
python run/internal/analyze_learning.py --action=summary

# View model performance
python run/internal/analyze_learning.py --action=models
```

## Docker Commands

### Container Management
```bash
# Start all services
docker compose up -d

# Start with GPU support
docker compose -f docker-compose.yml -f docker-compose.gpu.override.yml up -d

# Restart services
docker compose restart

# Stop services
docker compose down

# View logs
docker logs zeroai_api-prod --follow
docker logs zeroai_peer-prod --follow

# Access container shell
docker exec -it zeroai_api-prod bash
```

## Development Commands

### Testing
```bash
# Test internal crews individually
python run/testing/simple.py
python run/testing/code_generator.py
```

### Debugging
```bash
# Check peer discovery
python3 run/internal/peer_manager.py list

# Test specific crew categories
python run/internal/run_dev_ops.py --category=research "Research latest AI developments"
python run/internal/run_dev_ops.py --category=documentation "Update project documentation"
```

## Model Preferences by Crew Type

### Developer Crew
- **Best**: codellama:13b (specialized for code generation)
- **Good**: llama3.1:8b (strong generalist with good coding)
- **Fallback**: llama3.2:latest (capable generalist)
- **Local**: llama3.2:1b (lightweight, local fallback)

### Research Crew
- **Best**: llama3.1:8b (powerful reasoning and analysis)
- **Good**: llama3.2:latest (strong generalist for research)
- **Fallback**: gemma2:2b (smaller, still capable)
- **Local**: llama3.2:1b (lightweight, local fallback)

### Documentation Crew
- **Best**: llama3.2:latest (strong language generation)
- **Good**: llama3.1:8b (good alternative for text generation)
- **Fallback**: gemma2:2b (efficient for text tasks)
- **Local**: llama3.2:1b (lightweight, local fallback)

## Environment Variables

### Required Variables
```bash
# GitHub token for repository operations
export GH_TOKEN_CYFORD=your_github_token_here

# Ollama configuration
export OLLAMA_HOST=http://ollama:11434
export OLLAMA_MODEL_NAME=llama3.2:1b

# Debug levels
export PEER_DEBUG_LEVEL=3
export ROUTER_DEBUG_LEVEL=3
export ENABLE_PEER_LOGGING=true
export ENABLE_ROUTER_LOGGING=true
```

## Troubleshooting Commands

### Common Issues
```bash
# Check container status
docker ps

# Check available memory
free -h

# Check GPU availability
nvidia-smi

# Test peer connectivity
curl http://149.36.1.65:11434/api/tags

# Reset peer discovery cache
rm -f config/peers.json
```

## Project Structure Reference

```
ZeroAI/
├── API/                   # REST API endpoints
├── src/
│   ├── crews/
│   │   ├── internal/      # Internal working team agents
│   │   │   ├── developer/ # Development crew
│   │   │   ├── repo_manager/ # Repository management
│   │   │   ├── research/  # Research and analysis
│   │   │   └── documentation/ # Documentation crew
│   │   └── public/        # Public-facing crews
│   └── ai_dev_ops_crew.py # Secure entry point for internal crew
├── run/
│   └── internal/          # Internal crew runners
├── config/                # Configuration files
├── docker-compose.yml     # Main Docker configuration
└── docker-compose.gpu.override.yml # GPU support
```