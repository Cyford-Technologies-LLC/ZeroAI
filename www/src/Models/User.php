<?php
namespace Models;

use Core\Database;

class User extends BaseModel {
    
    public function authenticate($username, $password, $role = null) {
        $sql = "SELECT * FROM users WHERE username = ?";
        $params = [$username];
        
        if ($role) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch();
        
        return $user && password_verify($password, $user['password']);
    }
    
    public function create($username, $password, $role = 'user') {
        $stmt = $this->db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        return $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM users ORDER BY role DESC, username");
        return $stmt->fetchAll();
    }
    
    public function delete($username) {
        if ($username === 'admin') return false;
        $stmt = $this->db->prepare("DELETE FROM users WHERE username = ?");
        return $stmt->execute([$username]);
    }
}
?>