# 💰 ZeroAI

> **Zero Cost. Zero Cloud. Zero Limits. Build your own AI workforce that runs entirely on your hardware.**

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Python 3.8+](https://img.shields.io/badge/python-3.8+-blue.svg)](https://www.python.org/downloads/)
[![Ollama](https://img.shields.io/badge/Powered%20by-Ollama-orange)](https://ollama.ai/)
[![CrewAI](https://img.shields.io/badge/Framework-CrewAI-green)](https://crewai.com/)

## 🌟 Why ZeroAI?

- **💰 Zero Cost**: No API fees, no subscriptions, no hidden charges
- **🔒 Zero Cloud**: Your data never leaves your machine
- **⚡ Zero Limits**: Scale from prototype to enterprise on your terms
- **🌐 Smart Hybrid**: Optional cloud integration when you need it
- **🛠️ Zero Lock-in**: Fully customizable and open source

## 🎯 What Can You Build with ZeroAI?

- **🔬 Research Teams**: Automated data gathering and analysis
- **✍️ Content Creation**: Multi-agent writing and editing workflows  
- **📊 Business Intelligence**: Market research and competitive analysis
- **🎧 Customer Support**: Intelligent ticket routing and responses
- **👨💻 Code Review**: Automated code analysis and documentation
- **🤖 Personal Assistant**: Task automation and scheduling
- **🏢 Internal Working Teams**: Automated DevOps, security, and infrastructure management

## 🛠️ Internal Working Team Agents

ZeroAI includes specialized internal agents that can autonomously manage your development infrastructure:

### 👨💻 Developer Crew
- **Code Researcher (Dr. Alan Parse)**: Analyzes codebases and identifies issues
- **Senior Developer (Tony Kyles)**: Implements complex code solutions
- **Junior Developer (Tom Kyles)**: Handles routine development tasks
- **QA Engineer (Lara Croft)**: Ensures code quality through testing

### 🔧 DevOps & Infrastructure
- **Git Operator (Deon Sanders)**: Manages repository operations and version control
- **Documentation Agent**: Maintains project documentation automatically
- **Research Agent**: Gathers technical intelligence and best practices
- **Project Manager**: Orchestrates multi-agent workflows

### 🔒 Security & Maintenance
- **Security Auditor**: Scans for vulnerabilities and compliance issues
- **Infrastructure Monitor**: Tracks system health and performance
- **Automated Deployment**: Handles CI/CD pipelines and releases

### 🌐 Website Management
- **Content Manager**: Updates website content and documentation
- **SEO Optimizer**: Improves search engine visibility
- **Performance Monitor**: Tracks website metrics and optimization

These agents work together to provide a complete autonomous development and operations workforce that runs entirely on your infrastructure.

## 🚀 Quick Start

### Prerequisites
- **RAM**: 16GB minimum (32GB recommended for GPU)
- **Storage**: 10GB free space
- **OS**: Windows, macOS, or Linux
- **Docker**: Docker and Docker Compose installed
- **GPU** (Optional): NVIDIA GPU with Docker GPU support for enhanced performance

### 1-Minute Docker Setup

```bash
# Clone ZeroAI
git clone https://github.com/Cyford-Technologies-LLC/ZeroAI.git
cd ZeroAI

# For CPU-only systems
docker-compose up -d

# For GPU-enabled systems (NVIDIA)
docker-compose -f Docker-compose.yml -f docker-compose.gpu.override.yml up -d

# Access ZeroAI API at http://localhost:3939
# Access ZeroAI Peer Service at http://localhost:8080
```

### Windows Installation

```cmd
# Clone ZeroAI
git clone https://github.com/Cyford-Technologies-LLC/ZeroAI.git
cd ZeroAI

# For CPU-only systems
docker-compose up -d

# For GPU-enabled systems (NVIDIA with WSL2)
docker-compose -f Docker-compose.yml -f docker-compose.gpu.override.yml up -d
```

### Manual Setup (Alternative)

```bash
# Install Ollama (choose your platform)
# Linux/Mac:
curl -fsSL https://ollama.ai/install.sh | sh
# Windows: Download from https://ollama.ai/download

# Download AI model
ollama pull llama3.2:1b
ollama serve

# Install Python dependencies
pip install -r requirements.txt

# Launch your first ZeroAI crew
python examples/basic_crew.py
```

## 📋 ZeroAI Examples

### Basic Research Crew
```python
from src.zeroai import create_research_crew

# Create a research team
crew = create_research_crew()

# Execute research task
result = crew.kickoff(inputs={
    "topic": "Latest developments in renewable energy"
})

print(result)
```

### Internal DevOps Crew
```bash
# Run internal development tasks
python run/internal/run_dev_ops.py "Fix login validation bug"

# With specific project and repository
python run/internal/run_dev_ops.py --project=myapp --repo=https://github.com/user/repo.git "Add error handling"

# Dry run for testing
python run/internal/run_dev_ops.py --dry-run "Test task without changes"
```

### Smart Hybrid Mode
```python
from src.zeroai import ZeroAI

# Local processing (zero cost)
zero = ZeroAI(mode="local")

# Smart GPU routing (pay only when needed)
zero = ZeroAI(mode="smart", gpu_providers=["thunder", "prime"])

# Cloud integration (when you need maximum power)
zero = ZeroAI(mode="cloud", provider="openai")
```

### Cost Optimization
```python
# ZeroAI automatically routes tasks for optimal cost
zero = ZeroAI()

# Simple tasks → Local (free)
result1 = zero.process("Hello, how are you?")

# Complex tasks → GPU providers (only when needed)
result2 = zero.process("Comprehensive market analysis with strategic recommendations")
```

## 🏗️ ZeroAI Architecture

```
ZeroAI/
├── src/                    # Core ZeroAI framework
│   ├── agents/            # Pre-built agent templates
│   ├── crews/             # Agent crew definitions
│   │   ├── internal/      # Internal working team agents
│   │   │   ├── developer/ # Development crew
│   │   │   ├── repo_manager/ # Repository management
│   │   │   ├── research/  # Research and analysis
│   │   │   └── documentation/ # Documentation crew
│   │   └── public/        # Public-facing crews
│   ├── tasks/             # Task definitions
│   ├── providers/         # GPU and cloud providers
│   └── zeroai.py          # Main ZeroAI class
├── API/                   # REST API endpoints
├── run/                   # Execution scripts
│   └── internal/          # Internal crew runners
├── examples/              # Ready-to-run examples
├── config/                # Configuration files
├── Docker-compose.yml     # Main Docker configuration
├── docker-compose.gpu.override.yml # GPU support
├── docs/                  # Comprehensive documentation
└── tests/                 # Test suite
```

## 🔧 Configuration

Customize ZeroAI in `config/settings.yaml`:

```yaml
zeroai:
  mode: "smart"              # local, smart, or cloud
  cost_optimization: true    # Minimize costs automatically
  
model:
  name: "llama3.1:8b"
  temperature: 0.7

gpu_providers:
  enabled: true
  priority: ["thunder", "prime"]
  complexity_threshold: 7
```

## 💡 ZeroAI Modes

### 🏠 Local Mode (Zero Cost)
- All processing on your hardware
- Complete privacy and control
- No internet required after setup

### 🧠 Smart Mode (Optimal Cost)
- Intelligent task routing
- Local for simple tasks
- GPU providers for complex tasks
- Automatic cost optimization

### ☁️ Cloud Mode (Maximum Power)
- Access to latest models
- Unlimited processing power
- Pay-per-use pricing

## 📚 Documentation

- [📖 Complete Setup Guide](docs/setup.md)
- [🤖 Creating Custom Agents](docs/agents.md)
- [⚙️ Advanced Configuration](docs/configuration.md)
- [🔌 Provider Integration](docs/providers.md)
- [🚀 Deployment Guide](docs/deployment.md)

## 🤝 Contributing to ZeroAI

We welcome contributions! See our [Contributing Guide](CONTRIBUTING.md) for details.

1. Fork the ZeroAI repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

ZeroAI is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🌟 Star ZeroAI

If ZeroAI helps you build amazing AI solutions, please give us a star! ⭐

## 🔗 Links

- [Ollama Documentation](https://ollama.ai/docs)
- [CrewAI Framework](https://crewai.com/)
- [Llama 3.1 Model](https://ollama.ai/library/llama3.1)

---

**ZeroAI: Zero Cost. Zero Cloud. Zero Limits.** 💰🚀