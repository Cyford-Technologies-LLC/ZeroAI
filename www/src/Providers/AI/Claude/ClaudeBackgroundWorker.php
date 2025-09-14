<?php

namespace ZeroAI\Providers\AI\Claude;

class ClaudeBackgroundWorker {
    private $toolSystem;
    private $db;
    
    public function __construct() {
        $this->toolSystem = new ClaudeToolSystem();
        $this->db = new \ZeroAI\Core\DatabaseManager();
    }
    
    public function executeCommand($command, $args = []) {
        return $this->toolSystem->execute($command, $args, 'hybrid');
    }
    
    public function updateContext($key, $value) {
        try {
            $this->db->executeSQL("CREATE TABLE IF NOT EXISTS claude_context (key TEXT PRIMARY KEY, value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)", 'claude');
            $this->db->executeSQL("INSERT OR REPLACE INTO claude_context (key, value, updated_at) VALUES (?, ?, datetime('now'))", 'claude', [$key, $value]);
        } catch (\Exception $e) {
            error_log("Failed to update Claude context: " . $e->getMessage());
        }
    }
    
    public function getContext($key = null) {
        try {
            // Create table first
            $this->db->executeSQL("CREATE TABLE IF NOT EXISTS claude_context (key TEXT PRIMARY KEY, value TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)", 'claude');
            
            if ($key) {
                $result = $this->db->executeSQL("SELECT value FROM claude_context WHERE key = ?", 'claude', [$key]);
                return $result[0]['data'][0]['value'] ?? null;
            } else {
                $result = $this->db->executeSQL("SELECT key, value, updated_at FROM claude_context ORDER BY updated_at DESC", 'claude');
                return $result[0]['data'] ?? [];
            }
        } catch (\Exception $e) {
            return null;
        }
    }
}
?>