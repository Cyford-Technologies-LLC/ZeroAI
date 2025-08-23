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
- **👨‍💻 Code Review**: Automated code analysis and documentation
- **🤖 Personal Assistant**: Task automation and scheduling

## 🚀 Quick Start

### Prerequisites
- **RAM**: 16GB minimum (32GB recommended)
- **Storage**: 10GB free space
- **OS**: Windows, macOS, or Linux
- **Python**: 3.8 or higher

### 1-Minute Setup

```bash
# Clone ZeroAI
git clone https://github.com/yourusername/ZeroAI.git
cd ZeroAI

# Install Ollama (choose your platform)
# Linux/Mac:
curl -fsSL https://ollama.ai/install.sh | sh
# Windows: Download from https://ollama.ai/download

# Download AI model
ollama pull llama3.1:8b
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
│   ├── tasks/             # Task definitions
│   ├── providers/         # GPU and cloud providers
│   └── zeroai.py          # Main ZeroAI class
├── examples/              # Ready-to-run examples
├── config/                # Configuration files
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