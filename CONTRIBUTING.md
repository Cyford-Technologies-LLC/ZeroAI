# Contributing to ZeroAI

Thank you for your interest in contributing to ZeroAI! We welcome contributions from the community to help make AI accessible to everyone.

## 🚀 Getting Started

1. **Fork the ZeroAI repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/yourusername/ZeroAI.git
   cd ZeroAI
   ```
3. **Create a new branch** for your feature:
   ```bash
   git checkout -b feature/your-feature-name
   ```

## 🛠️ Development Setup

1. **Install dependencies**:
   ```bash
   pip install -r requirements.txt
   ```

2. **Set up Ollama**:
   ```bash
   ollama pull llama3.1:8b
   ollama serve
   ```

3. **Run ZeroAI tests**:
   ```bash
   python -m pytest tests/
   ```

## 📝 Contribution Guidelines

### Code Style
- Follow PEP 8 for Python code
- Use type hints where appropriate
- Add docstrings to all functions and classes
- Keep the ZeroAI philosophy: Zero Cost, Zero Cloud, Zero Limits

### Commit Messages
- Use clear, descriptive commit messages
- Start with a verb in present tense (e.g., "Add", "Fix", "Update")
- Reference issues when applicable
- Example: "Add Prime Intellect GPU provider support"

### Pull Requests
1. **Update documentation** if needed
2. **Add tests** for new functionality
3. **Ensure all tests pass**
4. **Update CHANGELOG.md** if applicable
5. **Follow ZeroAI branding** in user-facing features

## 🐛 Bug Reports

When reporting bugs, please include:
- **Description** of the issue
- **Steps to reproduce**
- **Expected behavior**
- **Actual behavior**
- **ZeroAI version and mode** (local/smart/cloud)
- **Environment details** (OS, Python version, etc.)

## 💡 Feature Requests

For feature requests, please provide:
- **Clear description** of the feature
- **Use case** and motivation
- **How it aligns with ZeroAI's mission** (zero cost, zero cloud, zero limits)
- **Proposed implementation** (if you have ideas)

## 🧪 Testing

- Write tests for new functionality
- Ensure existing tests still pass
- Test with different ZeroAI modes (local, smart, cloud)
- Test with different providers when applicable

## 📚 Documentation

- Update README.md for significant changes
- Add docstrings to new functions/classes
- Update configuration examples if needed
- Maintain ZeroAI branding and messaging

## 🎯 Areas for Contribution

We especially welcome contributions in these areas:

- **New GPU Providers**: Additional cloud GPU integrations
- **Agent Templates**: Specialized agents for different domains
- **Cost Optimization**: Better algorithms for cost/performance balance
- **Documentation**: Better guides and examples
- **Testing**: More comprehensive test coverage
- **UI/UX**: Web interface or GUI improvements
- **Performance**: Speed and efficiency improvements

## 📞 Getting Help

- **GitHub Issues**: For bugs and feature requests
- **Discussions**: For questions and general discussion
- **Documentation**: Check the docs/ folder for guides

## 🏆 Recognition

Contributors will be recognized in:
- README.md contributors section
- CHANGELOG.md for significant contributions
- GitHub contributors page
- ZeroAI Hall of Fame (coming soon!)

## 💰 ZeroAI Philosophy

When contributing, please keep ZeroAI's core philosophy in mind:

- **Zero Cost**: Prioritize free and open-source solutions
- **Zero Cloud**: Respect user privacy and local processing
- **Zero Limits**: Make AI accessible to everyone, everywhere

Thank you for helping make ZeroAI better! 🙏

---

**ZeroAI: Zero Cost. Zero Cloud. Zero Limits.** 💰🚀