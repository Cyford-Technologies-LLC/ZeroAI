# 🚀 Complete Setup Guide

This guide will walk you through setting up your self-hosted agentic AI system from scratch.

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

This starts the server on `http://localhost:11434`. Keep this terminal open.

### Step 4: Clone and Setup Project

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

### Step 5: Test Installation

```bash
# Run the basic example
python examples/basic_crew.py
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
  base_url: "http://localhost:11434"  # Ollama server URL

agents:
  max_concurrent: 3           # Maximum agents running simultaneously
  timeout: 300               # Task timeout in seconds
  verbose: true              # Show detailed output

logging:
  level: "INFO"              # Log level (DEBUG, INFO, WARNING, ERROR)
  file: "logs/ai_crew.log"   # Log file location
```

### Advanced Configuration

For production deployments, consider:

1. **Resource Limits**: Adjust `max_concurrent` based on your RAM
2. **Model Selection**: Use larger models for better quality (if you have enough RAM)
3. **Logging**: Set to "ERROR" for production to reduce log volume
4. **Timeouts**: Increase for complex tasks that take longer

## 🔧 Troubleshooting

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

## 🚀 Next Steps

Once your setup is working:

1. **Try the Examples**: Run different examples in the `examples/` folder
2. **Create Custom Agents**: Build agents for your specific use cases
3. **Integrate Tools**: Add external tools and APIs
4. **Scale Up**: Deploy on more powerful hardware for production use

## 📞 Getting Help

If you encounter issues:

1. **Check the logs**: Look in `logs/ai_crew.log` for error details
2. **GitHub Issues**: Report bugs or ask questions
3. **Documentation**: Check other files in the `docs/` folder
4. **Community**: Join discussions and share experiences

## 🎯 Verification Checklist

- [ ] Ollama installed and running
- [ ] Model downloaded successfully
- [ ] Python dependencies installed
- [ ] Basic example runs without errors
- [ ] Configuration file customized
- [ ] Logs directory created and writable

Congratulations! Your self-hosted agentic AI system is ready to use! 🎉