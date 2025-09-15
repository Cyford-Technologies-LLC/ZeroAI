<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeBackgroundWorker {
    private $toolSystem;
    private $db;
    
    public function __construct() {
        $this->toolSystem = new ClaudeToolSystem();
        $this->db = \ZeroAI\Core\DatabaseManager::getInstance();
    }
    
    public function executeCommand($command, $args = []) {
        return $this->toolSystem->execute($command, $args, 'hybrid');
    }
    
    public function updateContext($key, $value) {
        try {
            $this->db->query("CREATE TABLE IF NOT EXISTS claude_context (key TEXT PRIMARY KEY, value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
            $this->db->query("INSERT OR REPLACE INTO claude_context (key, value, updated_at) VALUES (?, ?, datetime('now'))", [$key, $value]);
        } catch (\Exception $e) {
            error_log("Failed to update Claude context: " . $e->getMessage());
        }
    }
    
    public function getContext($key = null) {
        try {
            // Create table first
            $this->db->query("CREATE TABLE IF NOT EXISTS claude_context (key TEXT PRIMARY KEY, value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
            
            if ($key) {
                $result = $this->db->query("SELECT value FROM claude_context WHERE key = ?", [$key]);
                return $result[0]['value'] ?? null;
            } else {
                $result = $this->db->query("SELECT key, value, updated_at FROM claude_context ORDER BY updated_at DESC");
                return $result ?? [];
            }
        } catch (\Exception $e) {
            return null;
        }
    }
}
?>