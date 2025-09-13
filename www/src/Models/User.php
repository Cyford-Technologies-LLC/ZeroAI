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
        $this->executeSQL("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT DEFAULT 'user',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    public function create(array $data): bool {
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $username = $data['username'];
        $role = $data['role'] ?? 'user';
        
        $result = $this->executeSQL(
            "INSERT INTO users (username, password, role) VALUES ('$username', '$hashedPassword', '$role')"
        );
        
        $this->lastInsertId = $result[0]['lastInsertId'] ?? null;
        return !isset($result[0]['error']);
    }
    
    public function getLastInsertId() {
        return $this->lastInsertId;
    }
    
    public function authenticate($username, $password) {
        $result = $this->executeSQL(
            "SELECT id, username, password, role FROM users WHERE username = '$username'"
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