<?php
namespace ZeroAI\Core;

class Company {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    public function getAll() {
        return $this->db->select('companies') ?: [];
    }
    
    public function getById($id) {
        $result = $this->db->select('companies', ['id' => $id]);
        return $result ? $result[0] : null;
    }
    
    public function create($data) {
        return $this->db->insert('companies', $data);
    }
    
    public function update($id, $data) {
        return $this->db->update('companies', $data, ['id' => $id]);
    }
    
    public function delete($id) {
        return $this->db->delete('companies', ['id' => $id]);
    }
}