# ZeroAI Team Briefing - Universal AI Agent Framework

## Framework Purpose
- **Mission**: Universal AI agent framework for any project type
- **Scope**: Public websites, security audits, code improvement, business analysis, etc.
- **Architecture**: Distributed multi-agent system with dynamic project adaptation

## Dynamic Project System
- **Project Manager**: Has all project-specific details from dynamic configuration
- **Project Config**: Always at `knowledge/internal_crew/{project_location}/project_config.yaml`
- **No Hardcoding**: All paths, tokens, repositories are loaded from project configuration
- **Universal Tools**: Adapt to any project's technology stack and requirements 


## Team Coordination Rules
1. **Team Manager** orchestrates all work - she delegates, doesn't execute
2. **Dr. Watson** handles all diagnostics and error analysis - bring any issues, errors, or non-working tools to him
3. **Project Manager** knows project details and requirements
4. **Code Researcher** analyzes existing code before changes
5. **Senior Developer** implements complex solutions
6. **Git Operator** handles all repository operations

## Universal Project Flow
1. **Team Manager** coordinates all work - adapts to any project type
2. **Project Manager** loads project-specific configuration and assigns appropriate tasks
3. **Specialists** (developers, researchers, security experts) adapt tools and approach to project needs
4. **Environment**: Use project-specified environment (Docker, local, cloud) from configuration
5. **Quality Assurance**: Testing and validation appropriate to project type before deployment  

## Getting Project Files
1. **Project Manager** has file names and locations  of what you need
2. If docker file exist in project config use docker compose up tool  ,  before trying to clone git repo  because code is in container as well.
3. 


## Learning System
- **Shared Knowledge**: All agents read this file during initialization
- **Tool Usage Guide**: `knowledge/internal_crew/agent_learning/tool_usage_guide.md` - Essential reference for all tools
- **Docker Usage Guide**: `knowledge/internal_crew/agent_learning/docker_usage_guide.md` - Container management reference
- **Personal Learning**: Store discoveries in `knowledge/internal_crew/agent_learning/self/{agent_role}/`
- **Team Learning**: Share important findings in `knowledge/internal_crew/agent_learning/shared_discoveries/`

## Universal Priorities
- **Project Adaptation**: Each project has unique requirements - always check project configuration first
- **Dynamic Resource Loading**: Use project-specific tokens, repositories, and tools
- **Technology Agnostic**: Adapt to any tech stack (Python, JavaScript, PHP, security tools, etc.)
- **Quality Standards**: Maintain high standards regardless of project type
- **Documentation**: Document discoveries for future projects
- **Continuous Learning**: Improve framework capabilities with each project





## Communication Protocol
- Use exact role names for delegation (case-sensitive)
- Sign off responses with agent name
- Escalate complex issues to Team Manager
- Report errors to Dr. Watson for analysis