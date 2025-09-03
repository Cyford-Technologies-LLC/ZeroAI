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
python -m run.internal.peer_manager --ip 149.36.1.65 --name GPU-01 --model codellama:13b add

# List peers
python -m run.internal.peer_manager list
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
clear ; python -m run.internal.simple_chat

# API crew test
clear ; python -m run.internal.api_crew

# Smart AI demo
clear ; python -m run.internal.smart_ai_demo

# Code generator
clear ; python -m run.internal.code_generator

# Basic crew example
clear ; python -m run.internal.basic_crew

# Advanced analysis
clear ; python -m run.examples.advanced_analysis
```

## Internal DevOps Commands

### Basic Usage
```bash
# Simple task
python -m run.internal.run_dev_ops "Fix a simple bug in the login form validation"

# With category specification
python -m run.internal.run_dev_ops --category=developer "Fix a simple bug in the login form validation"

# With project and repository
python -m run.internal.run_dev_ops --project=zeroai --repo=https://github.com/Cyford-Technologies-LLC/ZeroAI.git "Add error handling to the peer discovery system"

# Dry run for testing
python -m run.internal.run_dev_ops --dry-run "Test task that won't make changes"

# Verbose output
python -m run.internal.run_dev_ops -v "Task with detailed logging"
```

### Complex Examples
```bash
# Bug fix with repository operations
python -m run.internal.run_dev_ops "Fix a bug in the code where user login fails for repo https://github.com/myuser/my-test-app.git, update the README to reflect the change, and push the changes to a new branch 'fix-login'."

# Category-specific development task
python -m run.internal.run_dev_ops --category=developer "Fix a simple bug in the login form validation"

# Docker container usage
docker exec -it zeroai_api-prod python -m run.internal.run_dev_ops "Task description"
```

## Learning System Commands

### Analysis Commands
```bash
# View learning summary
python -m run.internal.analyze_learning --action=summary

# View model performance
python -m run.internal.analyze_learning --action=models
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
python -m run.testing.simple
python -m run.testing.code_generator
```

### Debugging
```bash
# Check peer discovery
python -m run.internal.peer_manager list

# Test specific crew categories
python -m run.internal.run_dev_ops --category=research "Research latest AI developments"
python -m run.internal.run_dev_ops --category=documentation "Update project documentation"
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

# Python path for module execution
export PYTHONPATH=/app:$PYTHONPATH

# Ollama configuration
export OLLAMA_HOST=http://ollama:11434
export OLLAMA_MODEL_NAME=llama3.2:1b

# Debug levels
export PEER_DEBUG_LEVEL=3
export ROUTER_DEBUG_LEVEL=3
export ENABLE_PEER_LOGGING=true
export ENABLE_ROUTER_LOGGING=true
```

### Docker Environment Variables
```bash
# Set in docker-compose.yml or .env file
PYTHONPATH=/app
OLLAMA_HOST=http://ollama:11434
GH_TOKEN_CYFORD=your_github_token_here
```

## Troubleshooting Commands

### Import Issues After Merge
```bash
# Fix all import issues automatically
python fix_imports.py

# Complete post-merge fix (imports + rebuild)
bash scripts/post-merge.sh
```

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