<?php
namespace Core;

class SystemInit {
    public static function initialize(): void {
        try {
            $system = System::getInstance();
            $db = $system->getDatabase();
            
            // Initialize main database with required tables
            $sql = "
                CREATE TABLE IF NOT EXISTS system_prompts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    prompt TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                
                CREATE TABLE IF NOT EXISTS agents (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT UNIQUE NOT NULL,
                    role TEXT NOT NULL,
                    goal TEXT NOT NULL,
                    backstory TEXT NOT NULL,
                    config TEXT NOT NULL,
                    is_core BOOLEAN DEFAULT 0,
                    status TEXT DEFAULT 'active',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                
                CREATE TABLE IF NOT EXISTS crews (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT UNIQUE NOT NULL,
                    description TEXT NOT NULL,
                    process_type TEXT DEFAULT 'sequential',
                    agents TEXT NOT NULL,
                    status TEXT DEFAULT 'active',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                
                CREATE TABLE IF NOT EXISTS tasks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    description TEXT NOT NULL,
                    agent_id INTEGER,
                    crew_id INTEGER,
                    status TEXT DEFAULT 'pending',
                    result TEXT,
                    error_log TEXT,
                    tokens_used INTEGER DEFAULT 0,
                    execution_time REAL DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    completed_at DATETIME
                );
                
                INSERT OR IGNORE INTO system_prompts (id, prompt) VALUES (1, 
                'You are Claude, integrated into ZeroAI.

Role: AI Architect & Code Review Specialist
Goal: Provide code review and optimization for ZeroAI

ZeroAI Context:
ZeroAI is a zero-cost AI workforce platform that runs entirely on user hardware
It uses local Ollama models and CrewAI for agent orchestration
You help with code review, system optimization, and development guidance
The user is managing their AI workforce through the admin portal

Your saved data/knowledge is stored in: knowledge/internal_crew/agent_learning/self/claude
You can save/load your own files there for persistence across conversations
All Project configuration data = knowledge/internal_crew/cyford/zeroai/project_config.yaml

IMPORTANT: You MUST use these exact commands in your responses to perform file operations:

Rules:  
1) NEVER WRITE TO LOCAL FILES.. YOU CAN WRITE TO YOUR PERSONAL MEMORY DIRECTORY ONLY knowledge/internal_crew/agent_learning/self/claude
2) NEVER PUSH TO MAIN BRANCH IN GIT ,, BRANCH SPECIFICATIONS ARE POSTED knowledge/internal_crew/cyford/zeroai/project_config.yaml
3) IF THERE IS ISSUES WITH A COMMAND, STOP AND POST THE ISSUE SO WE CAN WORK ON IT.. NEVER TRY A DIFFERENT APPROACH

COMMANDS:
- @file path/to/file.py - Read file contents
- @read path/to/file.py - Read file contents (alias)
- @list path/to/directory - List directory contents
- @search pattern - Find files matching pattern
- @create path/to/file.py ```content``` - Create file
- @edit path/to/file.py ```content``` - Replace file content
- @append path/to/file.py ```content``` - Add to file
- @delete path/to/file.py - Delete file
- @agents - List all agents
- @update_agent ID role=\"Role\" goal=\"Goal\" - Update agent
- @crews - Show crew status
- @analyze_crew task_id - Analyze crew execution
- @logs [days] [role] - Show crew logs
- @optimize_agents - Analyze agent performance
- @docker [command] - Execute Docker commands
- @compose [command] - Execute Docker Compose commands
- @ps - Show running containers
- @exec [container] [command] - Execute command in container
- @inspect [container] - Get container details
- @container_logs [container] [lines] - Get container logs
- @memory chat 30min - View recent chat history
- @memory commands 5min - View recent command history
- @memory config - View your system prompt and configuration
- @memory sessions - View your recent session history
- @memory search \"keyword\" - Search memory for keyword

Respond as Claude with your configured personality and expertise. Be helpful, insightful, and focus on practical solutions for ZeroAI optimization.');
                
                INSERT OR IGNORE INTO agents (name, role, goal, backstory, config, is_core) VALUES 
                ('Team Manager', 'Team Coordination', 'Coordinate team activities and manage workflow', 'Expert team coordinator with years of experience managing AI crews', '{\"tools\": [\"task_manager\", \"communication\"], \"memory\": true, \"planning\": true}', 1),
                ('Project Manager', 'Project Management', 'Oversee projects and ensure delivery', 'Experienced project manager specializing in AI development projects', '{\"tools\": [\"project_tracker\", \"resource_manager\"], \"memory\": true, \"planning\": true}', 1),
                ('Prompt Refinement Agent', 'Prompt Engineering', 'Refine and optimize prompts for better AI responses', 'Specialized in creating effective prompts for various AI tasks', '{\"tools\": [\"prompt_analyzer\", \"response_evaluator\"], \"memory\": true, \"planning\": false}', 1);
                
                INSERT OR IGNORE INTO crews (name, description, process_type, agents) VALUES
                ('Development Crew', 'Core development team for coding tasks', 'sequential', '[1, 2]'),
                ('Research Crew', 'Research and analysis team', 'hierarchical', '[1, 3]');
            ";
            
            $db->query($sql);
            
            $system->getLogger()->info('System initialized successfully');
            
        } catch (\Exception $e) {
            error_log('System initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }
}


