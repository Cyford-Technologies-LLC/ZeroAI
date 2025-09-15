<?php
namespace Controllers;

use Models\User;
use ZeroAI\Core\AuthenticationException;
use ZeroAI\Core\AuthorizationException;
use ZeroAI\Core\InputValidator;

class AdminController extends BaseController {
    
    public function login() {
        $this->render('admin/login');
    }
    
    public function authenticate() {
        try {
            // Validate CSRF token
            $csrfToken = $_POST['csrf_token'] ?? '';
            if (!InputValidator::validateCSRFToken($csrfToken)) {
                throw new AuthenticationException('Invalid CSRF token');
            }
            
            $username = InputValidator::sanitize($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                throw new AuthenticationException('Username and password required');
            }
            
            $user = new User();
            if ($user->authenticate($username, $password, 'admin')) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user'] = $username;
                header('Location: /admin/dashboard');
                return;
            }
            
            throw new AuthenticationException('Invalid credentials');
            
        } catch (AuthenticationException $e) {
            $this->render('admin/login', ['error' => $e->getMessage()]);
        }
    }
    
    public function dashboard() {
        $this->requireAuth();
        $this->render('admin/dashboard');
    }
    
    private function requireAuth() {
        if (!isset($_SESSION['admin_logged_in'])) {
            throw new AuthorizationException('Admin authentication required');
        }
    }
}
?>
