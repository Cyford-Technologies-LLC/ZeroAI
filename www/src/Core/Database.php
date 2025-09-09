<?php
namespace Core;

class Database {
    private $pdo;
    
    public function __construct() {
        $this->connect();
        $this->migrate();
    }
    
    private function connect() {
        try {
            $this->pdo = new \PDO("sqlite:" . DB_PATH);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function migrate() {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'user',
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
            completed_at DATETIME,
            FOREIGN KEY (agent_id) REFERENCES agents(id),
            FOREIGN KEY (crew_id) REFERENCES crews(id)
        );
        
        CREATE TABLE IF NOT EXISTS knowledge (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            type TEXT DEFAULT 'document',
            access_level TEXT DEFAULT 'all',
            agent_access TEXT,
            crew_access TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS system_metrics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            metric_name TEXT NOT NULL,
            metric_value TEXT NOT NULL,
            recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        INSERT OR IGNORE INTO users (username, password, role) VALUES 
        ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin'),
        ('user', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'user');
        
        INSERT OR IGNORE INTO agents (name, role, goal, backstory, config, is_core) VALUES 
        ('Team Manager', 'Team Coordination', 'Coordinate team activities and manage workflow', 'Expert team coordinator with years of experience managing AI crews', '{"tools": ["task_manager", "communication"], "memory": true, "planning": true}', 1),
        ('Project Manager', 'Project Management', 'Oversee projects and ensure delivery', 'Experienced project manager specializing in AI development projects', '{"tools": ["project_tracker", "resource_manager"], "memory": true, "planning": true}', 1),
        ('Prompt Refinement Agent', 'Prompt Engineering', 'Refine and optimize prompts for better AI responses', 'Specialized in creating effective prompts for various AI tasks', '{"tools": ["prompt_analyzer", "response_evaluator"], "memory": true, "planning": false}', 1);
        
        INSERT OR IGNORE INTO crews (name, description, process_type, agents) VALUES
        ('Development Crew', 'Core development team for coding tasks', 'sequential', '[1, 2]'),
        ('Research Crew', 'Research and analysis team', 'hierarchical', '[1, 3]');
        
        INSERT OR IGNORE INTO knowledge (title, content, type, access_level) VALUES
        ('ZeroAI Overview', 'ZeroAI is a zero-cost AI workforce platform that runs entirely on your hardware.', 'document', 'all'),
        ('CrewAI Best Practices', 'Guidelines for creating effective AI crews and task management.', 'guide', 'all');
        ";
        
        $this->pdo->exec($sql);
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}
?>