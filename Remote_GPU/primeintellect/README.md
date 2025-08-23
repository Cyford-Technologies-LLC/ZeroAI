# 🧠 ZeroAI + Prime Intellect GPU Bridge

Optional GPU bridge for users who want to deploy ZeroAI processing on Prime Intellect instances.

## 🎯 Use Case

- You have a Prime Intellect GPU instance
- You want ZeroAI to use that GPU for complex tasks
- You want to bridge your local ZeroAI to remote GPU power

## 🚀 Setup on Prime Intellect Instance

### 1. Clone ZeroAI Repository:
```bash
git clone https://github.com/Cyford-Technologies-LLC/ZeroAI.git
cd ZeroAI/Remote_GPU/primeintellect
```

### 2. Run Setup Script:
```bash
chmod +x setup.sh
./setup.sh
```

### 3. GPU Bridge will start on port 8001

## 🔧 Configure Local ZeroAI

### Update your local .env file:
```bash
PRIME_ENABLED=true
PRIME_GPU_BRIDGE_URL=http://your-instance-ip:8001
```

### Use ZeroAI normally:
```python
from src.zeroai import ZeroAI

# ZeroAI will automatically use your Prime Intellect GPU for complex tasks
zero = ZeroAI(mode="smart")
result = zero.analyze("complex market analysis")  # Uses your GPU!
```

## 📋 What This Does

- **Installs Ollama** on your Prime Intellect instance
- **Downloads AI models** (llama3.1:8b)
- **Starts GPU bridge API** on port 8001
- **Bridges ZeroAI** to your GPU instance

## 💰 Cost Benefits

- **Local tasks**: Free (runs on your machine)
- **Complex tasks**: Uses your Prime Intellect GPU ($0.16/hr)
- **Smart routing**: ZeroAI decides when to use GPU

---

**Optional component - only use if you want Prime Intellect GPU acceleration**