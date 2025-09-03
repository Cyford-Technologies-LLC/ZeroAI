# ZeroAI Team Briefing - Shared Knowledge Base

## Global Context
- **Project**: ZeroAI - Zero Cost AI Agent Framework
- **Mission**: Build autonomous AI workforce running entirely on local hardware
- **Architecture**: Distributed multi-agent system with GPU peer discovery

## Key System Details
- **Project Manager**: Sarah Connor has all project-specific details (repository, tokens, working directory)
- **GPU Server**: 149.36.1.65:11434 (primary compute node)
- **Local Fallback**: ollama:11434 (container local)
- **Project Config**: Located at `knowledge/internal_crew/{project_location}/project_config.yaml`
- **Dynamic Paths**: All project details are loaded dynamically based on current project 


## Team Coordination Rules
1. **Team Manager** orchestrates all work - she delegates, doesn't execute
2. **Dr. Watson** handles all diagnostics and error analysis - bring any issues, errors, or non-working tools to him
3. **Project Manager** knows project details and requirements
4. **Code Researcher** analyzes existing code before changes
5. **Senior Developer** implements complex solutions
6. **Git Operator** handles all repository operations

## Project Flow
1. **Team Manager** greets and responds to humans unless question is directed to specific agent
2. **Project Manager** has all project information (working directory, git URL, requirements) and assigns project tasks
3. **Senior Developers** handle complex coding with Code Researcher help, can delegate simple tasks to Junior Developer
4. **All project tasks** should be done in Docker environment using directory and git details from Project Manager
5. **Testing required** - QA Engineer must test code before Git Operator commits to repository  


## Learning System
- **Shared Knowledge**: All agents read this file during initialization
- **Personal Learning**: Store discoveries in `knowledge/internal_crew/agent_learning/{agent_role}/`
- **Team Learning**: Share important findings in `knowledge/internal_crew/agent_learning/shared_discoveries/`

## Current Priorities
- Each project and role has different priorities - check with Project Manager for specifics
- Continuously learn and improve at your assigned tasks
- Document all changes and improvements for team knowledge
- Fix import issues across internal crews
- Ensure all agents can communicate properly
- Maintain system stability and error handling



## Communication Protocol
- Use exact role names for delegation (case-sensitive)
- Sign off responses with agent name
- Escalate complex issues to Team Manager
- Report errors to Dr. Watson for analysis