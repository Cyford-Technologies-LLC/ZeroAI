<?php
namespace Controllers;

use Models\User;

class UserController extends BaseController {
    
    public function index() {
        $this->requireAuth();
        
        $user = new User();
        $users = $user->getAll();
        
        $message = $_SESSION['message'] ?? null;
        unset($_SESSION['message']);
        
        $this->render('admin/users', ['users' => $users, 'message' => $message]);
    }
    
    public function create() {
        $this->requireAuth();
        
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        
        if ($username && $password) {
            $user = new User();
            if ($user->create($username, $password, $role)) {
                $_SESSION['message'] = "User '$username' created successfully";
            } else {
                $_SESSION['message'] = "Failed to create user - username may already exist";
            }
        }
        
        $this->redirect('/admin/users');
    }
    
    public function delete() {
        $this->requireAuth();
        
        $username = $_POST['username'] ?? '';
        if ($username) {
            $user = new User();
            if ($user->delete($username)) {
                $_SESSION['message'] = "User '$username' deleted successfully";
            } else {
                $_SESSION['message'] = "Cannot delete user '$username'";
            }
        }
        
        $this->redirect('/admin/users');
    }
    
    private function requireAuth() {
        if (!isset($_SESSION['admin_logged_in'])) {
            $this->redirect('/admin');
        }
    }
}
?>
