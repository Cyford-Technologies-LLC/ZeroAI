<?php
namespace ZeroAI\Core;

class Project {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    public function create($data) {
        $data['secret_key'] = bin2hex(random_bytes(32));
        $data['slug'] = $this->generateSlug($data['name']);
        return $this->db->insert('projects', $data);
    }
    
    public function findById($id) {
        $result = $this->db->select('projects', ['id' => $id]);
        return $result ? $result[0] : null;
    }
    
    public function findByCompany($companyId) {
        return $this->db->select('projects', ['company_id' => $companyId]);
    }
    
    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('projects', $data, ['id' => $id]);
    }
    
    public function aiOptimizeDescription($id) {
        $project = $this->findById($id);
        if (!$project) return false;
        
        $aiDescription = "AI-enhanced project description: " . $project['description'];
        return $this->update($id, ['ai_description' => $aiDescription]);
    }
    
    public function getStats($id) {
        $project = $this->findById($id);
        if (!$project) return null;
        
        $tasks = $this->db->query("SELECT COUNT(*) as total, status FROM tasks WHERE project_id = ? GROUP BY status", [$id]);
        $bugs = $this->db->query("SELECT COUNT(*) as count FROM bugs WHERE project_id = ? AND status != 'closed'", [$id]);
        $milestones = $this->db->query("SELECT COUNT(*) as total, status FROM milestones WHERE project_id = ? GROUP BY status", [$id]);
        
        return [
            'tasks' => $tasks,
            'open_bugs' => $bugs[0]['count'] ?? 0,
            'milestones' => $milestones
        ];
    }
    
    private function generateSlug($name) {
        return strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
    }
}