<?php
namespace Controllers;

use Models\User;

class WebController extends BaseController {
    
    public function login() {
        $this->render('web/login');
    }
    
    public function authenticate() {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $user = new User();
        if ($user->authenticate($username, $password)) {
            $_SESSION['web_logged_in'] = true;
            $_SESSION['web_user'] = $username;
            $this->redirect('/web/frontend');
        }
        
        $this->render('web/login', ['error' => 'Invalid credentials']);
    }
    
    public function frontend() {
        $this->requireAuth();
        $this->render('web/frontend');
    }
    
    public function logout() {
        session_destroy();
        $this->redirect('/web');
    }
    
    private function requireAuth() {
        if (!isset($_SESSION['web_logged_in'])) {
            $this->redirect('/web');
        }
    }
}
?>


