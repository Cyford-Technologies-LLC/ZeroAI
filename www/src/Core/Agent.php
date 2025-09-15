<?php
namespace ZeroAI\Core;

class Agent {
    private $db;
    private $agentsDb;
    private $cache;
    private $queue;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->cache = CacheManager::getInstance();
        $this->queue = QueueManager::getInstance();
        $this->initializeAgentsDatabase();
    }
    
    private function initializeAgentsDatabase() {
        $agentsDbPath = __DIR__ . '/../../../data/agents.db';
        
        // Create data directory if it doesn't exist
        $dataDir = dirname($agentsDbPath);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        // Create agents.db if it doesn't exist
        if (!file_exists($agentsDbPath)) {
            $this->agentsDb = new \PDO('sqlite:' . $agentsDbPath);
            $this->createAgentsTable();
            $this->importDefaultAgents();
        } else {
            $this->agentsDb = new \PDO('sqlite:' . $agentsDbPath);
        }
    }
    
    private function createAgentsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS agents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            role TEXT NOT NULL,
            goal TEXT,
            backstory TEXT,
            tools TEXT,
            status TEXT DEFAULT 'active',
            is_core BOOLEAN DEFAULT 0,
            llm_model TEXT DEFAULT 'local',
            verbose BOOLEAN DEFAULT 0,
            allow_delegation BOOLEAN DEFAULT 1,
            allow_code_execution BOOLEAN DEFAULT 0,
            memory BOOLEAN DEFAULT 0,
            max_iter INTEGER DEFAULT 25,
            max_rpm INTEGER,
            max_execution_time INTEGER,
            max_retry_limit INTEGER DEFAULT 2,
            learning_enabled BOOLEAN DEFAULT 0,
            learning_rate REAL DEFAULT 0.05,
            feedback_incorporation TEXT DEFAULT 'immediate',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->agentsDb->exec($sql);
    }
    
    private function importDefaultAgents() {
        $sqlFile = __DIR__ . '/../../../data/all_agents_complete.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            // Remove DELETE statement for initial import
            $sql = preg_replace('/DELETE FROM agents;/', '', $sql);
            $this->agentsDb->exec($sql);
        }
    }
    
    public function create($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Queue the write operation
        $this->queue->push('agents', $data, 'INSERT');
        
        // Clear cache
        $this->cache->clearPattern('agents:*');
        
        return true;
    }
    
    public function findById($id) {
        try {
            $stmt = $this->agentsDb->prepare("SELECT * FROM agents WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $result = $this->db->select('agents', ['id' => $id]);
            return $result ? $result[0] : null;
        }
    }
    
    public function getAll() {
        // Check cache first
        $cacheKey = 'agents:all';
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Get from agents.db
        try {
            $stmt = $this->agentsDb->query("SELECT * FROM agents ORDER BY name");
            $agents = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Cache for 5 minutes
            $this->cache->set($cacheKey, $agents, 300);
            return $agents ?: [];
        } catch (\Exception $e) {
            $agents = $this->db->select('agents') ?: [];
            $this->cache->set($cacheKey, $agents, 300);
            return $agents;
        }
    }
    
    public function getActive() {
        try {
            $stmt = $this->agentsDb->prepare("SELECT * FROM agents WHERE status = 'active' ORDER BY name");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return $this->db->select('agents', ['status' => 'active']) ?: [];
        }
    }
    
    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Queue the write operation
        $this->queue->push('agents', ['data' => $data, 'where' => ['id' => $id]], 'UPDATE');
        
        // Clear cache
        $this->cache->clearPattern('agents:*');
        
        return true;
    }
    
    public function delete($id) {
        // Queue the write operation
        $this->queue->push('agents', ['where' => ['id' => $id]], 'DELETE');
        
        // Clear cache
        $this->cache->clearPattern('agents:*');
        
        return true;
    }
    
    public function getStats() {
        $agents = $this->getAll();
        return [
            'total' => count($agents),
            'active' => count(array_filter($agents, fn($a) => $a['status'] === 'active')),
            'roles' => count(array_unique(array_column($agents, 'role')))
        ];
    }
    
    public function getByRole($role) {
        return $this->db->select('agents', ['role' => $role, 'status' => 'active']) ?: [];
    }
    
    public function assignToCompany($agentId, $companyId) {
        return $this->db->insert('company_agents', [
            'agent_id' => $agentId,
            'company_id' => $companyId,
            'assigned_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function removeFromCompany($agentId, $companyId) {
        return $this->db->delete('company_agents', [
            'agent_id' => $agentId,
            'company_id' => $companyId
        ]);
    }
}


