<?php
namespace Models;

use Core\System;

class User {
    private $system;
    private $db;
    
    public function __construct() {
        $this->system = System::getInstance();
        $this->db = $this->system->getDatabase();
    }
    
    public function getAll(): array {
        try {
            $result = $this->db->executeSQL("SELECT id, username, role, created_at FROM users ORDER BY username");
            return $result[0]['data'] ?? [];
        } catch (\Exception $e) {
            $this->system->getLogger()->error('Failed to get users', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    public function create(string $username, string $password, string $role = 'user'): bool {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
            $result = $this->db->executeSQL("INSERT INTO users (username, password, role) VALUES ('$username', '$hashedPassword', '$role')");
            
            $this->system->getLogger()->info('User created', ['username' => $username, 'role' => $role]);
            return !isset($result[0]['error']);
        } catch (\Exception $e) {
            $this->system->getLogger()->error('Failed to create user', ['username' => $username, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function delete(int $id): bool {
        try {
            $result = $this->db->executeSQL("DELETE FROM users WHERE id = $id");
            $this->system->getLogger()->info('User deleted', ['id' => $id]);
            return !isset($result[0]['error']);
        } catch (\Exception $e) {
            $this->system->getLogger()->error('Failed to delete user', ['id' => $id, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function authenticate(string $username, string $password): ?array {
        try {
            $result = $this->db->executeSQL("SELECT id, username, password, role FROM users WHERE username = '$username'");
            
            if (!empty($result[0]['data'])) {
                $user = $result[0]['data'][0];
                if (password_verify($password, $user['password'])) {
                    $this->system->getLogger()->info('User authenticated', ['username' => $username]);
                    return ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']];
                }
            }
            
            $this->system->getLogger()->error('Authentication failed', ['username' => $username]);
            return null;
        } catch (\Exception $e) {
            $this->system->getLogger()->error('Authentication error', ['username' => $username, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    public function updatePassword(int $id, string $newPassword): bool {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $result = $this->db->executeSQL("UPDATE users SET password = '$hashedPassword' WHERE id = $id");
            
            $this->system->getLogger()->info('Password updated', ['id' => $id]);
            return !isset($result[0]['error']);
        } catch (\Exception $e) {
            $this->system->getLogger()->error('Failed to update password', ['id' => $id, 'error' => $e->getMessage()]);
            return false;
        }
    }
}