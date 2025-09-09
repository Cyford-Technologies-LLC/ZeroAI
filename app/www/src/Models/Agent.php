<?php
namespace Models;

class Agent extends BaseModel {
    
    public function create($name, $role, $goal, $backstory) {
        $stmt = $this->db->prepare("INSERT INTO agents (name, role, goal, backstory, config, is_core) VALUES (?, ?, ?, ?, ?, 0)");
        return $stmt->execute([$name, $role, $goal, $backstory, '{}']);
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM agents ORDER BY is_core DESC, name");
        return $stmt->fetchAll();
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("SELECT is_core FROM agents WHERE id = ?");
        $stmt->execute([$id]);
        $agent = $stmt->fetch();
        
        if ($agent && !$agent['is_core']) {
            $stmt = $this->db->prepare("DELETE FROM agents WHERE id = ?");
            return $stmt->execute([$id]);
        }
        return false;
    }
}
?>