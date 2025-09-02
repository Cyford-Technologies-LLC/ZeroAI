# ZeroAI Project Structure

## Complete Directory Hierarchy

```
ZeroAI/
├── API/                                    # REST API Layer
│   ├── __init__.py                        # Python package initialization
│   ├── api.py                             # FastAPI application with endpoints
│   ├── api-bkup.py                        # Backup API implementation
│   ├── learning_api.py                    # Learning system API endpoints
│   └── setup_api.sh                       # API setup script
├── backup/                                 # Backup files
│   ├── api-old.py                         # Legacy API implementation
│   ├── api.py                             # API backup
│   └── start_peer_service.py              # Peer service backup
├── config/                                 # Configuration Management
│   ├── peers.json                         # Peer discovery configuration
│   └── settings.yaml                      # Main application settings
├── docs/                                   # Documentation
│   ├── commands.md                        # Command reference guide
│   ├── developement_crews.md              # Development crew documentation
│   ├── project-structure.md               # This file
│   └── setup.md                           # Setup instructions
├── examples/                               # Example Scripts
│   ├── advanced_analysis.py               # Advanced analysis examples
│   ├── basic_crew.py                      # Basic crew usage
│   ├── cloud_integration.py               # Cloud provider integration
│   ├── code_generator.py                  # Code generation examples
│   ├── gpu_provider_demo.py               # GPU provider demonstrations
│   ├── hybrid_deployment.py               # Hybrid deployment examples
│   ├── manual_gpu_setup.py                # Manual GPU configuration
│   ├── peer_manager.py                    # Peer management examples
│   ├── peer_service.py                    # Peer service examples
│   ├── prime_runpod_demo.py               # Prime/RunPod integration
│   ├── simple_chat.py                     # Simple chat interface
│   ├── smart_ai_demo.py                   # Smart AI demonstrations
│   ├── start_peer_service.py              # Peer service startup
│   ├── stop_peer_service.py               # Peer service shutdown
│   └── zeroai_demo.py                     # ZeroAI feature demonstrations
├── knowledge/                              # Knowledge Base
│   ├── internal_crew/                     # Internal crew configurations
│   │   ├── cyford/                        # Cyford Technologies configs
│   │   │   └── zeroai/                    # ZeroAI project configs
│   │   │       └── project_config.yaml   # Project-specific settings
│   │   └── project_templates/             # Project template configurations
│   │       ├── project_config.yaml       # Template project config
│   │       ├── issue_templates.yaml      # Issue handling templates
│   │       └── style_guide.yaml          # Coding standards
│   └── cyford_technologies.md             # Company information
├── Remote_GPU/                             # Remote GPU Management
│   ├── primeintellect/                    # Prime Intellect integration
│   │   ├── .env.bridge                    # Environment configuration
│   │   ├── gpu_bridge.py                  # GPU bridge implementation
│   │   ├── instance_manager.py            # Instance management
│   │   ├── README.md                      # Prime Intellect documentation
│   │   ├── requirements.txt               # Python dependencies
│   │   ├── setup.sh                       # Setup script
│   │   └── start_persistent.sh            # Persistent service startup
│   └── README.md                          # Remote GPU documentation
├── run/                                    # Execution Scripts
│   ├── examples/                          # Example execution scripts
│   │   ├── advanced_analysis.py           # Advanced analysis runner
│   │   ├── basic_crew.py                  # Basic crew runner
│   │   ├── cloud_integration.py           # Cloud integration runner
│   │   ├── code_generator.py              # Code generation runner
│   │   ├── gpu_provider_demo.py           # GPU provider demo runner
│   │   ├── hybrid_deployment.py           # Hybrid deployment runner
│   │   ├── manual_gpu_setup.py            # Manual GPU setup runner
│   │   ├── peer_manager.py                # Peer management runner
│   │   ├── peer_service.py                # Peer service runner
│   │   ├── prime_runpod_demo.py           # Prime/RunPod demo runner
│   │   ├── simple_chat.py                 # Simple chat runner
│   │   ├── smart_ai_demo.py               # Smart AI demo runner
│   │   ├── start_peer_service.py          # Peer service startup runner
│   │   ├── stop_peer_service.py           # Peer service shutdown runner
│   │   └── zeroai_demo.py                 # ZeroAI demo runner
│   ├── internal/                          # Internal Operations
│   │   ├── advanced_analysis.py           # Internal advanced analysis
│   │   ├── analyze_learning.py            # Learning system analysis
│   │   ├── api_crew.py                    # API crew operations
│   │   ├── basic_crew.py                  # Internal basic crew
│   │   ├── code_generator.py              # Internal code generation
│   │   ├── code_generator2.py             # Alternative code generator
│   │   ├── peer_manager.py                # Internal peer management
│   │   ├── prime_runpod_demo.py           # Internal Prime/RunPod demo
│   │   ├── run_dev_ops.py                 # DevOps automation runner
│   │   ├── simple_chat.py                 # Internal chat interface
│   │   ├── smart_ai_demo.py               # Internal smart AI demo
│   │   └── start_peer_service_docker.py   # Docker peer service startup
│   └── testing/                           # Testing Scripts
│       ├── code_generator.py              # Code generation tests
│       └── simple.py                      # Simple functionality tests
├── setup/                                  # Setup Scripts
│   ├── setup_docker.sh                   # Docker setup
│   ├── setup-rl.sh                       # Reinforcement learning setup
│   ├── setup-ubuntu.sh                   # Ubuntu system setup
│   └── setup.bat                         # Windows setup batch file
├── src/                                    # Core Source Code
│   ├── agents/                            # Agent Definitions
│   │   ├── __init__.py                    # Agent package initialization
│   │   └── base_agents.py                 # Base agent implementations
│   ├── crews/                             # Crew Definitions
│   │   ├── classifier/                    # Task Classification Crew
│   │   │   ├── __init__.py               # Package initialization
│   │   │   ├── agents.py                 # Classifier agents
│   │   │   ├── tasks.py                  # Classification tasks
│   │   │   └── crew.py                   # Classifier crew assembly
│   │   ├── coding/                        # Code Development Crew
│   │   │   ├── __init__.py               # Package initialization
│   │   │   ├── agents.py                 # Coding agents
│   │   │   ├── tasks.py                  # Coding tasks
│   │   │   └── crew.py                   # Coding crew assembly
│   │   ├── customer_service/              # Customer Service Crew
│   │   │   ├── __init__.py               # Package initialization
│   │   │   ├── agents.py                 # Customer service agents
│   │   │   ├── tasks.py                  # Customer service tasks
│   │   │   └── crew.py                   # Customer service crew assembly
│   │   ├── internal/                      # Internal Operations Crews
│   │   │   ├── code_fixer/               # Code Fixing Crew
│   │   │   │   ├── __init__.py           # Package initialization
│   │   │   │   ├── agents.py             # Code fixing agents
│   │   │   │   ├── tasks.py              # Code fixing tasks
│   │   │   │   └── crew.py               # Code fixing crew assembly
│   │   │   ├── developer/                # Development Crew
│   │   │   │   ├── __init__.py           # Package initialization
│   │   │   │   ├── agents.py             # Developer agents (Dr. Alan Parse, Tony Kyles, Tom Kyles, Lara Croft)
│   │   │   │   ├── tasks.py              # Development tasks
│   │   │   │   └── crew.py               # Development crew assembly
│   │   │   ├── diagnostics/              # System Diagnostics Crew
│   │   │   │   ├── __init__.py           # Package initialization
│   │   │   │   ├── agents.py             # Diagnostic agents
│   │   │   │   ├── task.py               # Diagnostic tasks
│   │   │   │   ├── tools.py              # Diagnostic tools
│   │   │   │   └── crew.py               # Diagnostic crew assembly
│   │   │   ├── documentation/            # Documentation Crew
│   │   │   │   ├── __init__.py           # Package initialization
│   │   │   │   ├── agents.py             # Documentation agents
│   │   │   │   ├── tasks.py              # Documentation tasks
│   │   │   │   └── crew.py               # Documentation crew assembly
│   │   │   ├── repo_manager/             # Repository Management Crew
│   │   │   │   ├── __init__.py           # Package initialization
│   │   │   │   ├── agents.py             # Repository management agents (Deon Sanders)
│   │   │   │   ├── tasks.py              # Repository management tasks
│   │   │   │   └── crew.py               # Repository management crew assembly
│   │   │   ├── research/                 # Research Crew
│   │   │   │   ├── __init__.py           # Package initialization
│   │   │   │   ├── agents.py             # Research agents
│   │   │   │   ├── tasks.py              # Research tasks
│   │   │   │   └── crew.py               # Research crew assembly
│   │   │   ├── scheduler/                # Task Scheduling Crew
│   │   │   │   ├── __init__.py           # Package initialization
│   │   │   │   ├── agents.py             # Scheduling agents
│   │   │   │   ├── tasks.py              # Scheduling tasks
│   │   │   │   └── crew.py               # Scheduling crew assembly
│   │   │   ├── team_manager/             # Team Management Crew
│   │   │   │   ├── __init__.py           # Package initialization
│   │   │   │   ├── agents.py             # Team management agents
│   │   │   │   ├── tasks.py              # Team management tasks
│   │   │   │   ├── tools.py              # Team management tools
│   │   │   │   └── crew.py               # Team management crew assembly
│   │   │   ├── tools/                    # Internal Tools
│   │   │   │   ├── __init__.py           # Package initialization
│   │   │   │   ├── delegate_tool.py      # Task delegation tools
│   │   │   │   ├── docker_tool.py        # Docker management tools
│   │   │   │   ├── file_tool.py          # File manipulation tools
│   │   │   │   ├── git_tool.py           # Git operations tools
│   │   │   │   └── scheduling_tool.py    # Task scheduling tools
│   │   │   └── hierarchy.md              # Internal crew hierarchy documentation
│   │   ├── math/                          # Mathematical Operations Crew
│   │   │   ├── __init__.py               # Package initialization
│   │   │   ├── agents.py                 # Mathematical agents
│   │   │   ├── tasks.py                  # Mathematical tasks
│   │   │   └── crew.py                   # Mathematical crew assembly
│   │   ├── tech_support/                  # Technical Support Crew
│   │   │   ├── __init__.py               # Package initialization
│   │   │   ├── agents.py                 # Technical support agents
│   │   │   ├── tasks.py                  # Technical support tasks
│   │   │   └── crew.py                   # Technical support crew assembly
│   │   └── __init__.py                   # Crews package initialization
│   ├── learning/                          # Machine Learning System
│   │   ├── __init__.py                   # Learning package initialization
│   │   ├── adaptive_router.py            # Adaptive routing algorithms
│   │   ├── daemon.py                     # Learning system daemon
│   │   ├── feedback_loop.py              # Performance feedback system
│   │   ├── frontend_integration.py       # Frontend learning integration
│   │   └── task_manager.py               # Learning task management
│   ├── providers/                         # Cloud Provider Integrations
│   │   ├── __init__.py                   # Providers package initialization
│   │   ├── cloud_providers.py            # Generic cloud provider interface
│   │   ├── gpu_manager.py                # GPU resource management
│   │   ├── manual_gpu_provider.py        # Manual GPU configuration
│   │   ├── prime_bridge_provider.py      # Prime Intellect bridge provider
│   │   ├── prime_provider.py             # Prime Intellect provider
│   │   ├── runpod_provider.py            # RunPod provider integration
│   │   └── thunder_provider.py           # Thunder provider integration
│   ├── tasks/                             # Task Definitions
│   │   ├── __init__.py                   # Tasks package initialization
│   │   └── base_tasks.py                 # Base task implementations
│   ├── tools/                             # Tool Implementations
│   │   ├── file_tool.py                  # File manipulation tools
│   │   └── git_tool.py                   # Git operations tools
│   ├── utils/                             # Utility Functions
│   │   ├── custom_logger_callback.py     # Custom logging callbacks
│   │   ├── loop_detection.py             # Infinite loop detection
│   │   ├── memory.py                     # Memory management utilities
│   │   ├── tool_initializer.py           # Tool initialization utilities
│   │   └── yaml_utils.py                 # YAML processing utilities
│   ├── __init__.py                       # Source package initialization
│   ├── agent_communication.py            # Inter-agent communication
│   ├── ai_crew-api.py                    # API crew implementation
│   ├── ai_crew-old.py                    # Legacy crew implementation
│   ├── ai_crew.py                        # Main crew manager
│   ├── ai_dev_ops_crew.py                # DevOps crew manager (secure entry point)
│   ├── cache_manager.py                  # Caching system
│   ├── config.py                         # Configuration management
│   ├── dependencies.py                   # Dependency management
│   ├── devops_router.py                  # DevOps-specific routing
│   ├── distributed_router-orig.py        # Original distributed router
│   ├── distributed_router.py             # Distributed routing system
│   ├── env_loader.py                     # Environment variable loader
│   ├── intelligent_router.py             # Intelligent routing algorithms
│   ├── knowledge_base.py                 # Knowledge base management
│   ├── peer_discovery.py                 # Peer discovery system
│   ├── peers.yml                         # Peer configuration
│   ├── smart_ai_manager.py               # Smart AI management
│   ├── smart_router.py                   # Smart routing implementation
│   └── zeroai.py                         # Main ZeroAI class
├── tests/                                  # Test Suite
├── tools/                                  # Development Tools
│   ├── setup.MD                          # Tool setup documentation
│   └── todo                              # Development todo list
├── vendor/                                 # Third-party Dependencies
│   ├── composer/                         # Composer dependencies
│   │   ├── autoload_classmap.php         # Class autoloading
│   │   ├── autoload_namespaces.php       # Namespace autoloading
│   │   ├── autoload_psr4.php             # PSR-4 autoloading
│   │   ├── autoload_real.php             # Real autoloader
│   │   ├── autoload_static.php           # Static autoloader
│   │   ├── ClassLoader.php               # Class loader implementation
│   │   ├── installed.json                # Installed packages metadata
│   │   ├── installed.php                 # Installed packages PHP
│   │   ├── InstalledVersions.php         # Version management
│   │   └── LICENSE                       # Composer license
│   └── autoload.php                      # Main autoloader
├── .env                                    # Environment variables
├── .env.example                           # Environment variables template
├── .gitignore                             # Git ignore rules
├── composer.json                          # PHP dependencies
├── composer.lock                          # PHP dependency lock file
├── config.json                            # JSON configuration
├── CONTRIBUTING.md                        # Contribution guidelines
├── docker-compose.gpu.override.yml        # GPU Docker configuration
├── docker-compose.learning.yml            # Learning system Docker configuration
├── Docker-compose.yml                     # Main Docker configuration
├── Dockerfile                             # Docker image definition
├── gpu_instance_env.txt                   # GPU instance environment
├── LICENSE                                # Project license
├── prepare_resources.py                   # Resource preparation script
├── prepare_resources.sh                   # Resource preparation shell script
├── pyproject.toml                         # Python project configuration
├── README.md                              # Project documentation
├── requirements.txt                       # Python dependencies
├── run_example.py                         # Example runner
├── sample_commands.sh                     # Sample command reference
├── setup_env.py                           # Environment setup script
└── setup-docker.sh                       # Docker setup script
```

## Key Components

### Core Architecture
- **API/**: FastAPI REST endpoints for external access
- **src/**: Core application logic and agent implementations
- **run/**: Execution scripts for various operations
- **config/**: Configuration management and settings

### Agent System
- **Public Crews**: Customer service, coding, math, tech support
- **Internal Crews**: Developer, documentation, repo management, research
- **Specialized Agents**: 
  - Dr. Alan Parse (Code Researcher)
  - Tony Kyles (Senior Developer)
  - Tom Kyles (Junior Developer)
  - Lara Croft (QA Engineer)
  - Deon Sanders (Git Operator)

### Infrastructure
- **Distributed Router**: Intelligent task routing to optimal resources
- **Peer Discovery**: Automatic GPU server detection and management
- **Learning System**: Token-based performance optimization
- **Docker Deployment**: Containerized deployment with GPU support

### Security Model
- **Public API**: Safe, sandboxed operations via REST endpoints
- **Internal Operations**: Secure, authenticated DevOps automation
- **Isolated Execution**: Separate environments for different operation types

This structure provides a complete autonomous AI development and operations platform with zero external dependencies.