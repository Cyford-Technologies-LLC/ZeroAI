<?php
namespace ZeroAI\Core;

use PDO;

class ClaudeMemory {
    private $pdo;
    private $sessionId;
    
    public function __construct() {
        $dbPath = '/app/knowledge/internal_crew/agent_learning/self/claude/sessions_data/claude_memory.db';
        $this->pdo = new PDO("sqlite:$dbPath");
        $this->startSession();
    }
    
    private function startSession() {
        $stmt = $this->pdo->prepare("INSERT INTO sessions (start_time) VALUES (datetime('now'))");
        $stmt->execute();
        $this->sessionId = $this->pdo->lastInsertId();
    }
    
    public function saveChatMessage($sender, $message, $model) {
        $stmt = $this->pdo->prepare("
            INSERT INTO chat_history (sender, message, model_used, session_id) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$sender, $message, $model, $this->sessionId]);
        
        // Update session message count
        $this->pdo->prepare("UPDATE sessions SET message_count = message_count + 1 WHERE id = ?")
                  ->execute([$this->sessionId]);
    }
    
    public function saveCommand($command, $output, $status, $model) {
        $stmt = $this->pdo->prepare("
            INSERT INTO command_history (command, output, status, model_used, session_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$command, $output, $status, $model, $this->sessionId]);
        
        // Update session command count
        $this->pdo->prepare("UPDATE sessions SET command_count = command_count + 1 WHERE id = ?")
                  ->execute([$this->sessionId]);
    }
    
    public function getChatHistory($minutes, $model = null) {
        $sql = "SELECT * FROM chat_history WHERE timestamp >= datetime('now', '-{$minutes} minutes')";
        $params = [];
        
        if ($model) {
            $sql .= " AND model_used = ?";
            $params[] = $model;
        }
        
        $sql .= " ORDER BY timestamp DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCommandHistory($minutes, $model = null) {
        $sql = "SELECT * FROM command_history WHERE timestamp >= datetime('now', '-{$minutes} minutes')";
        $params = [];
        
        if ($model) {
            $sql .= " AND model_used = ?";
            $params[] = $model;
        }
        
        $sql .= " ORDER BY timestamp DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function searchMemory($keyword, $model = null) {
        $sql = "
            SELECT 'chat' as type, sender, message as content, model_used, timestamp 
            FROM chat_history WHERE message LIKE ?
            UNION ALL
            SELECT 'command' as type, 'system' as sender, command as content, model_used, timestamp 
            FROM command_history WHERE command LIKE ? OR output LIKE ?
        ";
        $params = ["%$keyword%", "%$keyword%", "%$keyword%"];
        
        if ($model) {
            $sql .= " AND model_used = ?";
            $params[] = $model;
        }
        
        $sql .= " ORDER BY timestamp DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
