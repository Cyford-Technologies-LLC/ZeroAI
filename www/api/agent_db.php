<?php
class AgentDB {
    private $db;
    
    public function __construct() {
        $this->db = new SQLite3('/app/data/agents.db');
        $this->initDB();
    }
    
    private function initDB() {
        $this->db->exec('CREATE TABLE IF NOT EXISTS agents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            role TEXT NOT NULL,
            goal TEXT,
            backstory TEXT,
            tools TEXT,
            status TEXT DEFAULT "active",
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    }
    
    public function getAllAgents() {
        $result = $this->db->query('SELECT * FROM agents ORDER BY id');
        $agents = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $agents[] = $row;
        }
        return $agents;
    }
    
    public function getAgent($id) {
        $stmt = $this->db->prepare('SELECT * FROM agents WHERE id = ?');
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    public function createAgent($data) {
        $stmt = $this->db->prepare('INSERT INTO agents (name, role, goal, backstory, tools, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bindValue(1, $data['name'], SQLITE3_TEXT);
        $stmt->bindValue(2, $data['role'], SQLITE3_TEXT);
        $stmt->bindValue(3, $data['goal'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(4, $data['backstory'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(5, $data['tools'] ?? '', SQLITE3_TEXT);
        $stmt->bindValue(6, $data['status'] ?? 'active', SQLITE3_TEXT);
        return $stmt->execute();
    }
    
    public function updateAgent($id, $data) {
        $fields = [];
        $values = [];
        
        foreach (['name', 'role', 'goal', 'backstory', 'tools', 'status'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql = 'UPDATE agents SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $values[] = $id;
        
        $stmt = $this->db->prepare($sql);
        foreach ($values as $i => $value) {
            $stmt->bindValue($i + 1, $value, SQLITE3_TEXT);
        }
        
        return $stmt->execute();
    }
    
    public function deleteAgent($id) {
        $stmt = $this->db->prepare('DELETE FROM agents WHERE id = ?');
        $stmt->bindValue(1, $id, SQLITE3_INTEGER);
        return $stmt->execute();
    }
}
?>