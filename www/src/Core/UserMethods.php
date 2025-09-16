<?php
namespace ZeroAI\Core;

trait UserMethods {
    
    public function getAllUsers($bypassCache = false) {
        return $this->query('SELECT * FROM users ORDER BY id', [], $bypassCache);
    }
    
    public function getUser($id, $bypassCache = false) {
        return $this->query('SELECT * FROM users WHERE id = ?', [$id], $bypassCache)[0] ?? null;
    }
    
    public function createUser($data, $bypassQueue = false) {
        return $this->insert('users', $data, $bypassQueue);
    }
    
    public function updateUser($id, $data, $bypassQueue = false) {
        return $this->update('users', $data, ['id' => $id], $bypassQueue);
    }
    
    public function deleteUser($id, $bypassQueue = false) {
        return $this->delete('users', ['id' => $id], $bypassQueue);
    }
    
    public function getActiveUsers($bypassCache = false) {
        return $this->query('SELECT * FROM users WHERE status = "active" ORDER BY id', [], $bypassCache);
    }
    
    public function getUserByEmail($email, $bypassCache = false) {
        return $this->query('SELECT * FROM users WHERE email = ?', [$email], $bypassCache)[0] ?? null;
    }
}
?>