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
        $result = $this->user->create($data);
        
        // If user created successfully and group specified, add to group
        if ($result && isset($data['group']) && $data['group']) {
            require_once __DIR__ . '/../Models/Group.php';
            $group = new \ZeroAI\Models\Group();
            $userId = $this->user->getLastInsertId();
            $group->addUserToGroup($userId, $data['group']);
        }
        
        return $result;
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