<?php
namespace Controllers;

use Models\User;

class AdminController extends BaseController {
    
    public function login() {
        $this->render('admin/login');
    }
    
    public function authenticate() {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $user = new User();
        if ($user->authenticate($username, $password, 'admin')) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $username;
            header('Location: /admin/dashboard');
            exit;
        }
        
        $this->render('admin/login', ['error' => 'Invalid credentials']);
    }
    
    public function dashboard() {
        $this->requireAuth();
        $this->render('admin/dashboard');
    }
    
    private function requireAuth() {
        if (!isset($_SESSION['admin_logged_in'])) {
            header('Location: /admin');
            exit;
        }
    }
}
?>