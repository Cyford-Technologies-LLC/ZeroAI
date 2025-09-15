<?php

namespace ZeroAI\Services;

use ZeroAI\Models\User;

class AuthService {
    private $user;
    
    public function __construct() {
        $this->user = new User();
    }
    
    public function login($username, $password) {
        $userData = $this->user->authenticate($username, $password);
        
        if ($userData && $userData['role'] === 'admin') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $username;
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['user_role'] = $userData['role'];
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];
    }
    
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            // Check if we're in web context
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($requestUri, '/web/') === 0) {
                header('Location: /web/login.php');
            } else {
                header('Location: /admin/login.php');
            }
            exit;
        }
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'username' => $_SESSION['admin_user'],
                'id' => $_SESSION['user_id'],
                'role' => $_SESSION['user_role']
            ];
        }
        return null;
    }
}