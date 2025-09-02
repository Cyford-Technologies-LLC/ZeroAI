# ZeroAI Changelog

All notable changes to the ZeroAI project will be documented in this file.

## [Unreleased] - 2025-01-02

### Added
- Distributed AI network with peer discovery system
- Agent-to-agent communication for task routing
- GPU node integration (149.36.1.65) with 21GB RAM and RTX capabilities
- Response caching system to avoid re-processing identical queries
- Smart model routing based on task complexity and system resources
- Peer service HTTP endpoints for capability exposure and task processing
- Code generation tool with distributed routing support

### Fixed
- **Critical**: Fixed `litellm.BadRequestError` by correcting model name format from `Ollama/` to `ollama/`
- **Critical**: Fixed NoneType string concatenation error in `create_project_manager_agent`
- **Critical**: Fixed research crew agent loading issues preventing coworker discovery
- Fixed distributed router FileNotFoundError typo
- Fixed OnlineSearchTool BaseTool implementation to work with CrewAI
- Fixed missing function call in research crew creation
- Fixed SerperDevTool error handling when API key is missing
- Fixed transformers package dependency issue

### Enhanced
- **tool_factory.py**: Added comprehensive error handling for all dependencies
- **research/agents.py**: Added robust import handling with fallbacks
- **research/tasks.py**: Improved task descriptions with structured requirements
- **distributed_router.py**: Reverted to stable Langchain Ollama implementation
- Added graceful degradation when optional components are missing

### Performance
- **Local Processing**: llama3.2:1b processes tasks in ~32 seconds
- **GPU Processing**: CodeLlama:13b processes tasks in ~3.6 seconds (9x faster)
- **Memory Optimization**: Configured for 3GB local VM with 256 max_tokens
- **Caching**: Prevents re-processing of identical requests

### Configuration
- Environment variables now take precedence over settings.yaml
- Added .env override system for flexible configuration
- Optimized model settings for resource-constrained environments
- Added peer discovery configuration with automatic capability detection

### Infrastructure
- **Local Node**: 3GB RAM, llama3.2:1b model
- **GPU Node**: 21GB RAM, 4 CPUs, RTX GPU with CodeLlama:13b and llama3.1:8b
- **Network**: UFW firewall configuration for peer communication
- **Services**: Background peer services with health monitoring

### Known Issues
- Team Manager only has itself as coworker due to research agent loading issues (partially resolved)
- Pydantic deprecation warnings from CrewAI dependencies (cosmetic only)
- SERPER_API_KEY required for online search functionality

### Dependencies
- Added: transformers package for token calculation
- Enhanced: crewai_tools with error handling
- Maintained: All existing functionality with backward compatibility

---

## Previous Versions

### [Initial Setup] - 2025-01-01
- Basic ZeroAI framework with Ollama integration
- CrewAI agent system implementation
- Local AI processing capabilities
- Configuration management system