<?php
namespace ZeroAI\Models;

class User {
    private $db;
    
    public function __construct() {
        require_once __DIR__ . '/../../config/database.php';
        $this->db = new \Database();
    }
    
    public function authenticate($username, $password) {
        $users = $this->db->select('users', ['username' => $username]);
        if (!empty($users) && password_verify($password, $users[0]['password'])) {
            return $users[0];
        }
        return false;
    }
    
    public function create($userData) {
        $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        return $this->db->insert('users', $userData);
    }
    
    public function findById($id) {
        $users = $this->db->select('users', ['id' => $id]);
        return !empty($users) ? $users[0] : null;
    }
    
    public function findByUsername($username) {
        $users = $this->db->select('users', ['username' => $username]);
        return !empty($users) ? $users[0] : null;
    }
}