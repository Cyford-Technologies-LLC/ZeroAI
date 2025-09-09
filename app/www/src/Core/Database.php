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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        INSERT OR IGNORE INTO users (username, password, role) VALUES 
        ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin'),
        ('user', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'user');
        
        INSERT OR IGNORE INTO agents (name, role, goal, backstory, config, is_core) VALUES 
        ('Team Manager', 'Team Coordination', 'Coordinate team activities', 'Expert manager', '{}', 1),
        ('Project Manager', 'Project Management', 'Oversee projects', 'Experienced PM', '{}', 1);
        ";
        
        $this->pdo->exec($sql);
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}
?>