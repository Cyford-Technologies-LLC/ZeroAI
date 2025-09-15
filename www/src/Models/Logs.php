<?php
namespace ZeroAI\Models;

class Logs extends BaseModel {
    protected $table = 'logs';
    
    public function __construct() {
        parent::__construct();
        $this->initTable();
    }
    
    protected function initTable() {
        $this->query("
            CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                message TEXT NOT NULL,
                context TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    public function getRecentLogs(string $type = 'ai', int $limit = 10): array {
        $result = $this->query("SELECT * FROM logs WHERE type = '$type' ORDER BY created_at DESC LIMIT $limit");
        $logs = $result[0]['data'] ?? [];
        
        // Format logs as strings
        return array_map(function($log) {
            return "[{$log['created_at']}] {$log['message']}";
        }, $logs);
    }
}
