# 🚀 Complete Setup Guide

This guide will walk you through setting up your self-hosted agentic AI system with **dynamic agent management**, **comprehensive admin portal**, and **advanced AI chat capabilities**.

## 🌟 New Features Highlights

### 🤖 Dynamic Agent Management System
- **Database-Driven Agents**: All agents now load from database instead of static files
- **Real-Time Configuration**: Edit agent settings through admin interface with immediate effect
- **Complete CrewAI Support**: Memory, learning, personality, communication style, tools, coworkers
- **Import System**: Automatically import existing agents from Python files

### 🎛️ Advanced Admin Portal
- **Agent Management**: Full CRUD operations with comprehensive configuration forms
- **Claude Integration**: Direct Claude chat with file access and crew supervision
- **Cloud Provider Management**: Configure OpenAI, Anthropic, and other cloud APIs
- **Real-Time Testing**: Built-in test pages to verify dynamic loading

### 💬 Multi-Modal Chat System
- **Claude Direct Chat**: File access with @file, @list, @search commands
- **Crew Chat**: Multi-agent conversations with specialized teams
- **Agent Chat**: Individual agent interactions with full context
- **Model Selection**: Choose between local and cloud models per conversation

## 📚 Related Documentation

- [📋 Commands Reference](commands.md) - Complete list of available commands
- [🏗️ Project Structure](project-structure.md) - Detailed project hierarchy
- [🤖 Internal AI System](internal-ai-system.md) - Growing agent ecosystem and knowledge base integration
- [📝 Changelog](../CHANGELOG.md) - Version history and updates

## 📋 Prerequisites

### System Requirements
- **RAM**: 16GB minimum (32GB recommended for better performance)
- **Storage**: 10GB free space for models and dependencies
- **CPU**: Modern multi-core processor (Intel i5/AMD Ryzen 5 or better)
- **OS**: Windows 10/11, macOS 10.15+, or Linux (Ubuntu 20.04+)

### Software Requirements
- **Python**: 3.8 or higher
- **Git**: For cloning the repository
- **Internet**: For initial model download (4-8GB)

## 🛠️ Installation Steps

### Step 1: Install Ollama

Ollama is the local inference server that will run your AI models.

#### Windows
1. Download the installer from [ollama.ai/download](https://ollama.ai/download)
2. Run the installer and follow the setup wizard
3. Ollama will be available in your system PATH

#### macOS
```bash
curl -fsSL https://ollama.ai/install.sh | sh
```

#### Linux
```bash
curl -fsSL https://ollama.ai/install.sh | sh
```

### Step 2: Download AI Model

```bash
# Download the Llama 3.1 8B model (recommended)
ollama pull llama3.1:8b

# Alternative models (optional):
# ollama pull llama3.1:70b    # Larger, more capable (requires 64GB+ RAM)
# ollama pull codellama:7b    # Specialized for code
# ollama pull mistral:7b      # Alternative general-purpose model
```

### Step 3: Start Ollama Server

```bash
ollama serve
```

This starts the server on `http://ollama:11434`. Keep this terminal open.

### Step 4: Clone and Setup Project

#### Manual Installation
```bash
# Clone the repository
git clone https://github.com/yourusername/ZeroAI.git
cd ZeroAI 

# Create virtual environment (recommended)
python -m venv venv

# Activate virtual environment
# Windows:
venv\Scripts\activate
# macOS/Linux:
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt
```

#### Docker Installation (Recommended)

**For CPU-only systems:**
```bash
# Clone the repository
git clone https://github.com/yourusername/ZeroAI.git
cd ZeroAI

# Start with Docker Compose
docker-compose up -d

# Access ZeroAI API at http://localhost:3939
# Access ZeroAI Peer Service at http://localhost:8080
```

**For GPU-enabled systems (NVIDIA):**
```bash
# Clone the repository
git clone https://github.com/yourusername/ZeroAI.git
cd ZeroAI

# Start with GPU support
docker-compose -f Docker-compose.yml -f docker-compose.gpu.override.yml up -d

# Access ZeroAI API at http://localhost:3939
# Access ZeroAI Peer Service at http://localhost:8080
```

**Docker Compose V1 (Legacy):**
```bash
# For older Docker installations using docker-compose v1
# CPU-only:
docker-compose up -d

# GPU-enabled:
docker-compose -f Docker-compose.yml -f docker-compose.gpu.override.yml up -d
```

**Docker Compose V2 (Modern):**
```bash
# For newer Docker installations using docker compose v2
# CPU-only:
docker compose up -d

# GPU-enabled:
docker compose -f Docker-compose.yml -f docker-compose.gpu.override.yml up -d
```

### Step 5: Test Installation

#### Manual Installation Testing
```bash
# Run the basic example
python examples/basic_crew.py
```

#### Docker Installation Testing
```bash
# Test API endpoint
curl http://localhost:3939/health

# Test peer service
curl http://localhost:8080/peers

# Run internal DevOps crew
python run/internal/run_dev_ops.py "Test system functionality"
```

If everything is working, you should see the AI crew start processing!

## ⚙️ Configuration

### Basic Configuration

Edit `config/settings.yaml` to customize your setup:

```yaml
model:
  name: "llama3.1:8b"          # Model to use
  temperature: 0.7             # Creativity level (0.0-2.0)
  max_tokens: 4096            # Maximum response length
  base_url: "http://ollama:11434"  # Ollama server URL

agents:
  max_concurrent: 3           # Maximum agents running simultaneously
  timeout: 300               # Task timeout in seconds
  verbose: true              # Show detailed output

logging:
  level: "INFO"              # Log level (DEBUG, INFO, WARNING, ERROR)
  file: "logs/ai_crew.log"   # Log file location
```

### 🔐 Optional: Persistent Secure Keys

For production deployments, set up persistent secure environment variables:

```bash
# Create secure system directory
sudo mkdir -p /etc/cyford/zeroai
sudo chmod 700 /etc/cyford/zeroai

# Create secure environment file
sudo tee /etc/cyford/zeroai/.env << EOF
# GitHub Tokens
GH_TOKEN_CYFORD=your_private_token_here
GITHUB_TOKEN_TESTCORP=your_test_token_here

# Other secure variables
OPENAI_API_KEY=your_openai_key_here
ANTHROPIC_API_KEY=your_anthropic_key_here
EOF

# Secure permissions (root only)
sudo chmod 600 /etc/cyford/zeroai/.env
sudo chown root:root /etc/cyford/zeroai/.env
```

**Benefits:**
- ✅ **Survives Docker rebuilds** - Tokens persist across container recreations
- ✅ **Secure permissions** - Only root can read/write
- ✅ **Not in repository** - Tokens never committed to git
- ✅ **Automatic loading** - Docker automatically loads these variables
- ✅ **Fallback support** - Still works with local `.env` for development

### Advanced Configuration

For production deployments, consider:

1. **Resource Limits**: Adjust `max_concurrent` based on your RAM
2. **Model Selection**: Use larger models for better quality (if you have enough RAM)
3. **Logging**: Set to "ERROR" for production to reduce log volume
4. **Timeouts**: Increase for complex tasks that take longer

## 🐳 Docker-Specific Configuration

### Docker Prerequisites
- **Docker**: Docker Desktop or Docker Engine installed
- **Docker Compose**: V1 (docker-compose) or V2 (docker compose)
- **GPU Support** (Optional): NVIDIA Docker runtime for GPU acceleration
- **Memory**: 8GB minimum for Docker containers

### Docker Environment Variables
Create a `.env` file in the project root:
```bash
# GitHub Token for repository access
GITHUB_TOKEN_TESTCORP=your_github_token_here
GH_TOKEN_CYFORD=your_private_token_here

# Model preferences
OLLAMA_MODEL=llama3.1:8b
OLLAMA_HOST=http://ollama:11434

# API Configuration
API_PORT=3939
PEER_PORT=8080
```

### Docker Commands
```bash
# View running containers
docker ps

# View logs
docker-compose logs -f

# Stop services
docker-compose down

# Rebuild containers
docker-compose up -d --build

# Access container shell
docker-compose exec zeroai bash
```

## 🔧 Troubleshooting

### Docker Issues

#### "Port already in use" error
- **Cause**: Ports 3939 or 8080 already occupied
- **Solution**: 
  - Stop conflicting services: `docker ps` and `docker stop <container>`
  - Or change ports in `Docker-compose.yml`

#### "No such image" error
- **Cause**: Docker images not built
- **Solution**: Run `docker-compose up -d --build`

#### GPU not detected in Docker
- **Cause**: NVIDIA Docker runtime not installed
- **Solution**: Install nvidia-docker2 package

### Common Issues

#### "Connection refused" error
- **Cause**: Ollama server not running
- **Solution**: Run `ollama serve` in a separate terminal

#### "Model not found" error
- **Cause**: Model not downloaded
- **Solution**: Run `ollama pull llama3.1:8b`

#### Out of memory errors
- **Cause**: Insufficient RAM for the model
- **Solution**: 
  - Close other applications
  - Use a smaller model: `ollama pull llama3.1:7b`
  - Reduce `max_concurrent` in config

#### Slow performance
- **Causes**: Limited RAM, CPU, or running other heavy applications
- **Solutions**:
  - Close unnecessary applications
  - Reduce `temperature` for faster responses
  - Use a smaller model
  - Increase system RAM if possible

### Performance Optimization

1. **Model Selection**:
   - `llama3.1:8b`: Best balance of quality and speed
   - `llama3.1:7b`: Faster, uses less RAM
   - `llama3.1:70b`: Highest quality (requires 64GB+ RAM)

2. **System Optimization**:
   - Close unnecessary applications
   - Use SSD storage for better I/O
   - Ensure adequate cooling for sustained performance

3. **Configuration Tuning**:
   - Lower `temperature` for faster, more focused responses
   - Adjust `max_tokens` based on your needs
   - Reduce `max_concurrent` if experiencing memory issues

## 🎛️ Admin Portal Setup

### Access the Admin Interface

After installation, access the comprehensive admin portal:

```bash
# Docker installation
http://localhost:8080/admin

# Manual installation (if running web server)
http://localhost:8080/admin
```

**Default Credentials:**
- Username: `admin`
- Password: `admin123`

### Admin Portal Features

#### 🤖 Dynamic Agent Management
1. **Import Existing Agents**: Click "Import All Existing Agents" to populate database
2. **Edit Agent Configuration**: Full CrewAI options including:
   - Memory and learning capabilities
   - Personality traits and communication style
   - Tools, knowledge sources, and coworkers
   - Delegation settings and execution limits
3. **Test Dynamic Loading**: Use the test page to verify agents are using database settings

#### 💬 Chat Interfaces
1. **Claude Direct Chat**: 
   - File access with `@file path/to/file.py`
   - Directory listing with `@list path/`
   - File search with `@search pattern`
   - Agent management with `@agents` and `@update_agent`

2. **Crew Chat**: Multi-agent team conversations
3. **Agent Chat**: Individual specialized agent interactions

#### ⚙️ Configuration Management
1. **Cloud Providers**: Configure API keys for OpenAI, Anthropic, etc.
2. **Claude Settings**: Customize role, goals, and supervisor model selection
3. **System Settings**: Adjust logging, performance, and security settings

### Quick Start with Admin Portal

1. **Login to Admin**: Navigate to `/admin` and login
2. **Import Agents**: Go to Agent Management → Import All Existing Agents
3. **Test Dynamic Loading**: Visit Test Dynamic Agents page
4. **Configure Claude**: Set up Claude API key in Cloud AI Settings
5. **Start Chatting**: Use Claude Direct Chat or Crew Chat

## 🚀 Next Steps

Once your setup is working:

1. **Explore Admin Portal**: Configure agents, test chat interfaces, manage settings
2. **Import Your Agents**: Use the import feature to migrate existing agent configurations
3. **Try Chat Interfaces**: Test Claude direct chat, crew conversations, and agent interactions
4. **Customize Agents**: Edit agent personalities, tools, and capabilities through the admin interface
5. **Scale Up**: Deploy on more powerful hardware for production use

## 📞 Getting Help

If you encounter issues:

1. **Check the logs**: Look in `logs/ai_crew.log` for error details
2. **GitHub Issues**: Report bugs or ask questions
3. **Documentation**: Check other files in the `docs/` folder
4. **Community**: Join discussions and share experiences

## 🎯 Verification Checklist

### Manual Installation
- [ ] Ollama installed and running
- [ ] Model downloaded successfully
- [ ] Python dependencies installed
- [ ] Basic example runs without errors
- [ ] Configuration file customized
- [ ] Logs directory created and writable

### Docker Installation
- [ ] Docker and Docker Compose installed
- [ ] Environment variables configured
- [ ] Containers running: `docker ps`
- [ ] API accessible: `curl http://localhost:3939/health`
- [ ] Peer service accessible: `curl http://localhost:8080/peers`
- [ ] Internal crew functional: `python run/internal/run_dev_ops.py "test"`

### Admin Portal Verification
- [ ] Admin portal accessible: `http://localhost:8080/admin`
- [ ] Login successful with admin/admin123
- [ ] Agent import completed successfully
- [ ] Dynamic agent test shows "SUCCESS" status
- [ ] Claude chat interface functional (if API key configured)
- [ ] Crew chat responds to messages
- [ ] Agent configuration forms show all CrewAI options

### Dynamic Agent System
- [ ] Database contains imported agents with full configurations
- [ ] Agents show memory, learning, personality settings
- [ ] Crew execution uses database agents (not static files)
- [ ] Admin changes take effect immediately
- [ ] Test page confirms dynamic loading is working

### Optional: Secure Keys Setup
- [ ] System secure directory created: `/etc/cyford/zeroai/`
- [ ] Secure `.env` file created with proper permissions
- [ ] GitHub tokens configured and accessible
- [ ] Docker containers can access secure environment variables
- [ ] Claude API key configured for advanced chat features

## 🎉 System Architecture Overview

### Dynamic Agent Loading Flow
1. **Database First**: Crews attempt to load agents from SQLite database
2. **Full Configuration**: Agents include all CrewAI options (memory, learning, personality, etc.)
3. **Static Fallback**: If database loading fails, system falls back to Python files
4. **Real-Time Updates**: Admin changes take effect immediately without restart

### Admin Portal Architecture
- **Frontend**: PHP-based admin interface with dynamic navigation
- **Backend**: SQLite database with comprehensive agent/crew/task schemas
- **API Integration**: Direct Claude API integration with model selection
- **File Access**: Claude can read project files using specialized commands

### Chat System Architecture
- **Multi-Modal**: Support for direct AI chat, crew conversations, and agent interactions
- **Model Routing**: Intelligent routing between local Ollama and cloud APIs
- **Context Management**: Persistent conversation history and file context
- **Command System**: Special commands for file access and agent management

## 📖 Next Steps

After successful installation:

1. **Explore Admin Portal**: Navigate through all sections to understand capabilities
2. **Import and Configure**: Import your existing agents and customize through the interface
3. **Test Chat Systems**: Try all three chat modes (Claude, Crew, Agent)
4. **Read Documentation**: Check out the [Internal AI System](internal-ai-system.md) to understand the growing agent ecosystem
5. **Review Commands**: Use the [Commands Reference](commands.md) for available operations
6. **Explore Structure**: Understand the [Project Structure](project-structure.md)
7. **Check Updates**: Review the [Changelog](../CHANGELOG.md) for latest features

## 🏆 Key Achievements

✅ **Dynamic Agent Management**: Database-driven agent configuration with real-time updates  
✅ **Comprehensive Admin Portal**: Full-featured web interface for system management  
✅ **Advanced Chat Capabilities**: Multiple AI interaction modes with file access  
✅ **Cloud Integration**: Seamless integration with Claude, OpenAI, and other providers  
✅ **Complete CrewAI Support**: All agent options available through admin interface  
✅ **Production Ready**: Secure, scalable architecture with proper error handling  

Congratulations! Your advanced self-hosted agentic AI system with dynamic management is ready to use! 🎉