<?php
namespace ZeroAI\Core;

class Tenant {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    public function create($data) {
        $data['secret_key'] = bin2hex(random_bytes(32));
        return $this->db->insert('tenants', $data);
    }
    
    public function findById($id) {
        $result = $this->db->select('tenants', ['id' => $id]);
        return $result ? $result[0] : null;
    }
    
    public function findByDomain($domain) {
        $result = $this->db->select('tenants', ['domain' => $domain]);
        return $result ? $result[0] : null;
    }
    
    public function getAll() {
        return $this->db->select('tenants');
    }
    
    public function update($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db->update('tenants', $data, ['id' => $id]);
    }
}


