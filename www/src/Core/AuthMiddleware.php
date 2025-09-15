<?php
namespace ZeroAI\Core;

class AuthMiddleware {
    private $userManager;
    
    public function __construct() {
        $this->userManager = new UserManager();
    }
    
    public function requireAuth() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            header('Location: /admin/login.php');
            exit;
        }
        return $_SESSION;
    }
    
    public function requireRole($role) {
        $this->requireAuth();
        if ($_SESSION['user_role'] !== $role && $_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            die('Access denied');
        }
    }
    
    public function requirePermission($permission) {
        $this->requireAuth();
        if (!$this->userManager->hasPermission($_SESSION['user_id'], $permission)) {
            http_response_code(403);
            die('Access denied');
        }
    }
    
    public function isDemo() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'demo';
    }
    
    public function canModify() {
        return !$this->isDemo();
    }
    
    public function requireNotDemo($message = 'Demo users cannot perform this action') {
        if ($this->isDemo()) {
            http_response_code(403);
            die('<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px;">' . $message . '</div>');
        }
    }
    
    public function getCurrentUser() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['user_name'] ?? 'Unknown',
            'role' => $_SESSION['user_role'] ?? 'user'
        ];
    }
}
