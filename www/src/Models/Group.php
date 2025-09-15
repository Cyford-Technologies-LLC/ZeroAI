<?php

namespace ZeroAI\Models;

use ZeroAI\Core\DatabaseManager;

class Group extends BaseModel {
    protected $table = 'groups';
    
    public function __construct() {
        parent::__construct();
        $this->initTable();
    }
    
    protected function initTable() {
        $this->db->executeSQL("
            CREATE TABLE IF NOT EXISTS groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE NOT NULL,
                description TEXT,
                permissions TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $this->db->executeSQL("
            CREATE TABLE IF NOT EXISTS user_groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                group_id INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (group_id) REFERENCES groups(id)
            )
        ");
        
        $this->createDefaultGroups();
    }
    
    private function createDefaultGroups() {
        $groups = [
            ['name' => 'admin', 'description' => 'Full system access', 'permissions' => 'admin,frontend'],
            ['name' => 'frontend', 'description' => 'Frontend portal access only', 'permissions' => 'frontend'],
            ['name' => 'viewer', 'description' => 'Read-only access', 'permissions' => '']
        ];
        
        foreach ($groups as $group) {
            $existing = $this->db->executeSQL("SELECT id FROM groups WHERE name = ?", [$group['name']]);
            if (empty($existing[0]['data'])) {
                $this->db->executeSQL(
                    "INSERT INTO groups (name, description, permissions) VALUES (?, ?, ?)",
                    [$group['name'], $group['description'], $group['permissions']]
                );
            }
        }
    }
    
    public function getUserGroups($userId) {
        $result = $this->db->executeSQL("
            SELECT g.* FROM groups g 
            JOIN user_groups ug ON g.id = ug.group_id 
            WHERE ug.user_id = ?
        ", [$userId]);
        
        return $result[0]['data'] ?? [];
    }
    
    public function addUserToGroup($userId, $groupId) {
        $existing = $this->db->executeSQL(
            "SELECT id FROM user_groups WHERE user_id = ? AND group_id = ?",
            [$userId, $groupId]
        );
        
        if (empty($existing[0]['data'])) {
            return $this->db->executeSQL(
                "INSERT INTO user_groups (user_id, group_id) VALUES (?, ?)",
                [$userId, $groupId]
            );
        }
        
        return true;
    }
    
    public function removeUserFromGroup($userId, $groupId) {
        return $this->db->executeSQL(
            "DELETE FROM user_groups WHERE user_id = ? AND group_id = ?",
            [$userId, $groupId]
        );
    }
    
    public function hasPermission($userId, $permission) {
        $groups = $this->getUserGroups($userId);
        
        foreach ($groups as $group) {
            $permissions = explode(',', $group['permissions']);
            if (in_array($permission, $permissions)) {
                return true;
            }
        }
        
        return false;
    }
}
