<?php

namespace ZeroAI\Models;

use ZeroAI\Core\DatabaseManager;

class User extends BaseModel {
    protected $table = 'users';
    private $lastInsertId;
    
    public function __construct() {
        parent::__construct();
        $this->initTable();
    }
    
    protected function initTable() {
        $this->db->executeSQL("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT DEFAULT 'user',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    public function create($data) {
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $result = $this->db->executeSQL(
            "INSERT INTO users (username, password, role) VALUES (?, ?, ?)",
            [$data['username'], $hashedPassword, $data['role']]
        );
        
        $this->lastInsertId = $result[0]['lastInsertId'] ?? null;
        return $result;
    }
    
    public function getLastInsertId() {
        return $this->lastInsertId;
    }
    
    public function authenticate($username, $password) {
        $result = $this->db->executeSQL(
            "SELECT id, username, password, role FROM users WHERE username = ?",
            [$username]
        );
        
        if (!empty($result[0]['data'])) {
            $user = $result[0]['data'][0];
            if (password_verify($password, $user['password'])) {
                return ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']];
            }
        }
        
        return null;
    }
    
}