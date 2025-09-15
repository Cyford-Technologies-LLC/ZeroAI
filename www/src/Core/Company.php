<?php
namespace ZeroAI\Core;

class Company {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    public function create($data) {
        return $this->db->insert('companies', $data);
    }
    
    public function findById($id) {
        $result = $this->db->select('companies', ['id' => $id]);
        return $result ? $result[0] : null;
    }
    
    public function findByTenant($tenantId) {
        return $this->db->select('companies', ['tenant_id' => $tenantId]);
    }
    
    public function getAll() {
        return $this->db->select('companies');
    }
    
    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('companies', $data, ['id' => $id]);
    }
}
