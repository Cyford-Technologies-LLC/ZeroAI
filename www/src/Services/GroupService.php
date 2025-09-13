<?php

namespace ZeroAI\Services;

use ZeroAI\Models\Group;

class GroupService {
    private $group;
    
    public function __construct() {
        $this->group = new Group();
    }
    
    public function getAllGroups() {
        return $this->group->getAll();
    }
    
    public function createGroup($data) {
        return $this->group->create($data);
    }
    
    public function getUserGroups($userId) {
        return $this->group->getUserGroups($userId);
    }
    
    public function addUserToGroup($userId, $groupId) {
        return $this->group->addUserToGroup($userId, $groupId);
    }
    
    public function removeUserFromGroup($userId, $groupId) {
        return $this->group->removeUserFromGroup($userId, $groupId);
    }
    
    public function hasPermission($userId, $permission) {
        return $this->group->hasPermission($userId, $permission);
    }
    
    public function canAccessAdmin($userId) {
        return $this->hasPermission($userId, 'admin');
    }
    
    public function canAccessFrontend($userId) {
        return $this->hasPermission($userId, 'frontend');
    }
}