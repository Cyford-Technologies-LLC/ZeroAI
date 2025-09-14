<?php
namespace ZeroAI\Core;

class DatabaseManager {
    private $db;
    
    public function __construct() {
        require_once __DIR__ . '/../../config/database.php';
        $this->db = new \Database();
    }
    
    public function select($table, $where = [], $limit = null) {
        return $this->db->select($table, $where, $limit);
    }
    
    public function insert($table, $data) {
        return $this->db->insert($table, $data);
    }
    
    public function update($table, $data, $where) {
        return $this->db->update($table, $data, $where);
    }
    
    public function delete($table, $where) {
        return $this->db->delete($table, $where);
    }
    
    public function executeSQL($sql) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [['data' => $result]];
    }
    
    public function getTokenUsage() {
        return [
            'total_tokens' => 0,
            'tokens_today' => 0,
            'cost_today' => 0.00,
            'requests_today' => 0
        ];
    }
    
    public function logTokenUsage($tokens, $cost = 0) {
        return true;
    }
}