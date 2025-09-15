<?php
namespace ZeroAI\Core;

class Project {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    public function create($data) {
        return $this->db->insert('projects', $data);
    }
    
    public function findById($id) {
        $result = $this->db->select('projects', ['id' => $id]);
        return $result ? $result[0] : null;
    }
    
    public function findByCompany($companyId) {
        return $this->db->select('projects', ['company_id' => $companyId]);
    }
    
    public function getAll() {
        return $this->db->select('projects');
    }
    
    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('projects', $data, ['id' => $id]);
    }
}


