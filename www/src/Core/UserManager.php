<?php
namespace ZeroAI\Core;

class UserManager {
    private $db;
    
    public function __construct() {
        require_once __DIR__ . '/../../config/database.php';
        $this->db = new \Database();
        $this->initializeUserSchema();
    }
    
    private function initializeUserSchema() {
        $pdo = $this->db->getConnection();
        
        // Add new columns to existing users table
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN permissions TEXT DEFAULT '[]'");
        } catch (\PDOException $e) {
            // Column already exists
        }
        
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN status TEXT DEFAULT 'active'");
        } catch (\PDOException $e) {
            // Column already exists
        }
        
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_login DATETIME");
        } catch (\PDOException $e) {
            // Column already exists
        }
        
        // Create demo user if not exists
        $this->createUserIfNotExists('demo', 'demo123', 'demo', []);
        
        // Create frontend user if not exists
        $this->createUserIfNotExists('frontend', 'frontend123', 'frontend', ['view_public']);
    }
    
    private function createUserIfNotExists($username, $password, $role, $permissions) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, permissions) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $username, 
                password_hash($password, PASSWORD_DEFAULT), 
                $role,
                json_encode($permissions)
            ]);
        }
    }
    
    public function authenticate($username, $password) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND (status IS NULL OR status = 'active')");
        $stmt->execute([$username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = datetime('now') WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            return $user;
        }
        return false;
    }
    
    public function hasPermission($userId, $permission) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT role, permissions FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$user) return false;
        if ($user['role'] === 'admin') return true;
        if ($user['role'] === 'demo') return false;
        
        $permissions = json_decode($user['permissions'] ?? '[]', true) ?: [];
        return in_array($permission, $permissions);
    }
    
    public function getAllUsers() {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->query("SELECT id, username, role, status, last_login, created_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function updateUser($id, $data) {
        $pdo = $this->db->getConnection();
        $fields = [];
        $values = [];
        
        $allowedFields = ['username', 'role', 'permissions', 'status'];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = ?";
                $values[] = is_array($value) ? json_encode($value) : $value;
            }
        }
        
        if (empty($fields)) return false;
        
        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        return $pdo->prepare($sql)->execute($values);
    }
    
    public function changePassword($userId, $newPassword, $currentPassword = null) {
        $pdo = $this->db->getConnection();
        
        if ($currentPassword) {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                return false;
            }
        }
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
    }
    
    public function createUser($username, $password, $role = 'user', $permissions = []) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, permissions) VALUES (?, ?, ?, ?)");
        return $stmt->execute([
            $username, 
            password_hash($password, PASSWORD_DEFAULT), 
            $role,
            json_encode($permissions)
        ]);
    }
}