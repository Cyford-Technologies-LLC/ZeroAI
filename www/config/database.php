<?php
class Database {
    private $db_path = '/app/data/zeroai.db';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("sqlite:" . $this->db_path);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->initTables();
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function initTables() {
        $adminPassword = password_hash(getenv('ADMIN_PASSWORD') ?: 'admin123', PASSWORD_DEFAULT);
        $userPassword = password_hash('user123', PASSWORD_DEFAULT);
        
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
        
        INSERT OR IGNORE INTO users (username, password, role) VALUES 
        ('admin', '$adminPassword', 'admin'),
        ('user', '$userPassword', 'user');
        
        INSERT OR IGNORE INTO agents (name, role, goal, backstory, config, is_core) VALUES 
        ('Team Manager', 'Team Coordination Specialist', 'Coordinate team activities', 'Expert in team management', '{\"tools\": [\"delegate_tool\"], \"memory\": true}', 1),
        ('Project Manager', 'Project Management Expert', 'Oversee project execution', 'Experienced project manager', '{\"tools\": [\"file_tool\"], \"memory\": true}', 1),
        ('Prompt Refinement Agent', 'Prompt Optimization Specialist', 'Refine prompts for better responses', 'Expert in prompt engineering', '{\"tools\": [\"learning_tool\"], \"memory\": true}', 1);
        ";
        
        $this->pdo->exec($sql);
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}
?>