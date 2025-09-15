<?php
namespace ZeroAI\Models;

class Agent extends BaseModel {
    protected $table = 'agents';
    
    public function __construct() {
        parent::__construct();
        $this->initTable();
    }
    
    protected function initTable() {
        $this->query("
            CREATE TABLE IF NOT EXISTS agents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                role TEXT NOT NULL,
                goal TEXT,
                backstory TEXT,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    public function getActiveAgents(): array {
        $result = $this->query("SELECT * FROM agents WHERE status = 'active'");
        return $result[0]['data'] ?? [];
    }
    
    public function getAgentStats(): array {
        $total = $this->query("SELECT COUNT(*) as count FROM agents");
        $active = $this->query("SELECT COUNT(*) as count FROM agents WHERE status = 'active'");
        
        return [
            'total' => $total[0]['data'][0]['count'] ?? 0,
            'active' => $active[0]['data'][0]['count'] ?? 0
        ];
    }
}
