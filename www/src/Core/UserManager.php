<?php
namespace ZeroAI\Core;

class UserManager {
    private $db;
    private $cache;
    private $logger;
    
    public function __construct() {
        require_once __DIR__ . '/../../config/database.php';
        $this->db = new \Database();
        $this->cache = \ZeroAI\Core\CacheManager::getInstance();
        $this->logger = \ZeroAI\Core\Logger::getInstance();
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
        
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT");
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
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND (status IS NULL OR status = 'active')");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Use Database class update method with queue
            $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);
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
        $this->logger->info('UserManager: getAllUsers() called');
        
        try {
            $pdo = $this->db->getConnection();
            $this->logger->info('UserManager: Database connection established');
            
            $stmt = $pdo->query("SELECT id, username, email, role, status, last_login, created_at FROM users ORDER BY created_at DESC");
            $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->logger->info('UserManager: Retrieved ' . count($users) . ' users from database');
            
            return $users;
        } catch (\Exception $e) {
            $this->logger->error('UserManager: Error in getAllUsers(): ' . $e->getMessage());
            return [];
        }
    }
    
    public function updateUser($id, $data) {
        $pdo = $this->db->getConnection();
        $fields = [];
        $values = [];
        
        $allowedFields = ['username', 'email', 'role', 'permissions', 'status'];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = ?";
                $values[] = is_array($value) ? json_encode($value) : $value;
            }
        }
        
        if (empty($fields)) return false;
        
        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $result = $pdo->prepare($sql)->execute($values);
        
        if ($result) {
            $this->cache->delete('users_all');
            $this->cache->delete('user_' . $id);
        }
        
        return $result;
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
    
    public function createUser($username, $password, $role = 'user', $permissions = [], $email = null) {
        $this->logger->info("UserManager: Creating user '$username' with role '$role'");
        
        try {
            $pdo = $this->db->getConnection();
            
            // Check for duplicate username or email
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR (email IS NOT NULL AND email = ?)");
            $checkStmt->execute([$username, $email]);
            if ($checkStmt->fetchColumn() > 0) {
                $this->logger->warning("UserManager: Duplicate username or email for '$username'");
                throw new \Exception('Username or email already exists');
            }
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, permissions, email, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', datetime('now'))");
            $result = $stmt->execute([
                $username, 
                password_hash($password, PASSWORD_DEFAULT), 
                $role,
                json_encode($permissions),
                $email
            ]);
            
            if ($result) {
                $this->logger->info("UserManager: Successfully created user '$username'");
            } else {
                $this->logger->error("UserManager: Failed to create user '$username'");
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('UserManager: Error creating user: ' . $e->getMessage());
            throw $e;
        }
    }
}


