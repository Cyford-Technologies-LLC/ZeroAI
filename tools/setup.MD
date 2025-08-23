# 🤖 Self-Hosted Agentic AI - Quick Setup

> **Note**: This file has been moved! For the complete setup guide, see [docs/setup.md](../docs/setup.md)

## ⚡ Quick Start (5 minutes)

```bash
# 1. Install Ollama
curl -fsSL https://ollama.ai/install.sh | sh  # Linux/Mac
# Windows: Download from https://ollama.ai/download

# 2. Download model and start server
ollama pull llama3.1:8b
ollama serve

# 3. Clone and run
git clone https://github.com/yourusername/hosted_AI.git
cd ZeroAI
pip install -r requirements.txt
python examples/basic_crew.py
```

## 🎯 What You Get

- **🔒 100% Local**: No cloud dependencies
- **💰 $0 Cost**: No API fees or subscriptions  
- **🚀 Production Ready**: Scale from prototype to enterprise
- **🛠️ Customizable**: Build agents for any use case

## 📚 Full Documentation

- [📖 Complete Setup Guide](../docs/setup.md)
- [🤖 Agent Creation](../docs/agents.md)
- [⚙️ Configuration](../docs/configuration.md)
- [🔧 Troubleshooting](../docs/setup.md#troubleshooting)

**Ready to build your AI workforce? Start with the [complete setup guide](../docs/setup.md)!**