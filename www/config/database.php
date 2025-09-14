<?php
/**
 * Database Connection Manager
 * 
 * Handles SQLite database connections and table initialization for ZeroAI.
 * Creates default users and agents on first run.
 */
class Database {
    private $db_path = '/app/data/zeroai.db';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("sqlite:" . $this->db_path);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initTables();
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function initTables() {
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        ";
        
        $this->pdo->exec($sql);
        
        // Insert default users safely
        $this->insertDefaultUser('admin', getenv('ADMIN_PASSWORD') ?: 'admin123', 'admin');
        $this->insertDefaultUser('demo', 'demo123', 'demo');
        
        // Insert default agents safely
        $this->insertDefaultAgent('Team Manager', 'Team Coordination Specialist', 'Coordinate team activities', 'Expert in team management', '{"tools": ["delegate_tool"], "memory": true}', 1);
        $this->insertDefaultAgent('Project Manager', 'Project Management Expert', 'Oversee project execution', 'Experienced project manager', '{"tools": ["file_tool"], "memory": true}', 1);
        $this->insertDefaultAgent('Prompt Refinement Agent', 'Prompt Optimization Specialist', 'Refine prompts for better responses', 'Expert in prompt engineering', '{"tools": ["learning_tool"], "memory": true}', 1);
    }
    
    private function insertDefaultUser($username, $password, $role) {
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
    }
    
    private function insertDefaultAgent($name, $role, $goal, $backstory, $config, $isCore) {
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO agents (name, role, goal, backstory, config, is_core) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $role, $goal, $backstory, $config, $isCore]);
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}
?>