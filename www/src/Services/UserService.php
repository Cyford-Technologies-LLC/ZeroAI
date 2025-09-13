<?php

namespace ZeroAI\Services;

use ZeroAI\Models\User;

class UserService {
    private $user;
    
    public function __construct() {
        $this->user = new User();
    }
    
    public function authenticate($username, $password) {
        return $this->user->authenticate($username, $password);
    }
    
    public function getAllUsers() {
        return $this->user->getAll();
    }
    
    public function createUser($data) {
        return $this->user->create($data);
    }
    
    public function deleteUser($id) {
        return $this->user->delete($id);
    }
    
    public function getUserStats() {
        $users = $this->getAllUsers();
        return [
            'total' => count($users),
            'admin' => count(array_filter($users, fn($u) => $u['role'] === 'admin')),
            'user' => count(array_filter($users, fn($u) => $u['role'] === 'user'))
        ];
    }
}