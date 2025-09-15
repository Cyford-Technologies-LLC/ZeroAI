<?php
namespace ZeroAI\Core;

use PDO;

class ClaudeMemoryInit {
    public static function initialize() {
        $memoryDir = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data';
        if (!is_dir($memoryDir)) {
            mkdir($memoryDir, 0777, true);
        }
        
        $dbPath = $memoryDir . '/claude_memory.db';
        $pdo = new PDO("sqlite:$dbPath");
        
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            end_time DATETIME,
            primary_model TEXT,
            message_count INTEGER DEFAULT 0,
            command_count INTEGER DEFAULT 0
        )");
        
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender TEXT NOT NULL,
            message TEXT NOT NULL,
            model_used TEXT NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            session_id INTEGER,
            FOREIGN KEY (session_id) REFERENCES sessions(id)
        )");
        
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS command_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            command TEXT NOT NULL,
            output TEXT,
            status TEXT NOT NULL,
            model_used TEXT NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            session_id INTEGER,
            FOREIGN KEY (session_id) REFERENCES sessions(id)
        )");
        
        return ['success' => true, 'message' => 'Claude memory database initialized'];
    }
}
