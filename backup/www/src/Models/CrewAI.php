<?php
namespace Models;

class CrewAI extends BaseModel {
    
    public function getAgents() {
        $stmt = $this->db->query("SELECT * FROM agents ORDER BY is_core DESC, name");
        return $stmt->fetchAll();
    }
    
    public function createAgent($name, $role, $goal, $backstory, $tools = [], $memory = false, $planning = false) {
        $config = json_encode([
            'tools' => $tools,
            'memory' => $memory,
            'planning' => $planning,
            'reasoning' => false,
            'testing' => false
        ]);
        
        $stmt = $this->db->prepare("INSERT INTO agents (name, role, goal, backstory, config, is_core) VALUES (?, ?, ?, ?, ?, 0)");
        return $stmt->execute([$name, $role, $goal, $backstory, $config]);
    }
    
    public function getCrews() {
        $stmt = $this->db->query("SELECT * FROM crews ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function createCrew($name, $description, $process_type = 'sequential', $agents = []) {
        $stmt = $this->db->prepare("INSERT INTO crews (name, description, process_type, agents, created_at) VALUES (?, ?, ?, ?, datetime('now'))");
        return $stmt->execute([$name, $description, $process_type, json_encode($agents)]);
    }
    
    public function getTasks() {
        $stmt = $this->db->query("SELECT * FROM tasks ORDER BY created_at DESC LIMIT 50");
        return $stmt->fetchAll();
    }
    
    public function createTask($description, $agent_id, $crew_id = null) {
        $stmt = $this->db->prepare("INSERT INTO tasks (description, agent_id, crew_id, status, created_at) VALUES (?, ?, ?, 'pending', datetime('now'))");
        return $stmt->execute([$description, $agent_id, $crew_id]);
    }
    
    public function getSystemMetrics() {
        return [
            'total_agents' => $this->db->query("SELECT COUNT(*) FROM agents")->fetchColumn(),
            'total_crews' => $this->db->query("SELECT COUNT(*) FROM crews")->fetchColumn(),
            'pending_tasks' => $this->db->query("SELECT COUNT(*) FROM tasks WHERE status = 'pending'")->fetchColumn(),
            'completed_tasks' => $this->db->query("SELECT COUNT(*) FROM tasks WHERE status = 'completed'")->fetchColumn(),
            'failed_tasks' => $this->db->query("SELECT COUNT(*) FROM tasks WHERE status = 'failed'")->fetchColumn()
        ];
    }
}
?>