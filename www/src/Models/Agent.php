<?php
namespace Models;

use Core\System;

class Agent {
    private $system;
    private $db;
    
    public function __construct() {
        $this->system = System::getInstance();
        $this->db = $this->system->getDatabase();
    }
    
    public function getAll(): array {
        try {
            $result = $this->db->executeSQL("SELECT * FROM agents ORDER BY is_core DESC, name");
            return $result[0]['data'] ?? [];
        } catch (\Exception $e) {
            $this->system->getLogger()->error('Failed to get agents', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    public function create(string $name, string $role, string $goal, string $backstory = '', array $config = []): bool {
        try {
            $configJson = json_encode($config);
            $sql = "INSERT INTO agents (name, role, goal, backstory, config, is_core, status) VALUES ('$name', '$role', '$goal', '$backstory', '$configJson', 0, 'active')";
            $result = $this->db->executeSQL($sql);
            
            $this->system->getLogger()->info('Agent created', ['name' => $name]);
            return !isset($result[0]['error']);
        } catch (\Exception $e) {
            $this->system->getLogger()->error('Failed to create agent', ['name' => $name, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function update(int $id, array $data): bool {
        try {
            $updates = [];
            foreach (['name', 'role', 'goal', 'backstory', 'status'] as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = '" . addslashes($data[$field]) . "'";
                }
            }
            
            if (isset($data['config'])) {
                $updates[] = "config = '" . json_encode($data['config']) . "'";
            }
            
            if (!empty($updates)) {
                $sql = "UPDATE agents SET " . implode(', ', $updates) . " WHERE id = $id";
                $result = $this->db->executeSQL($sql);
                
                $this->system->getLogger()->info('Agent updated', ['id' => $id]);
                return !isset($result[0]['error']);
            }
            
            return false;
        } catch (\Exception $e) {
            $this->system->getLogger()->error('Failed to update agent', ['id' => $id, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function delete(int $id): bool {
        try {
            // Check if core agent
            $result = $this->db->executeSQL("SELECT is_core FROM agents WHERE id = $id");
            if (!empty($result[0]['data']) && $result[0]['data'][0]['is_core']) {
                $this->system->getLogger()->error('Cannot delete core agent', ['id' => $id]);
                return false;
            }
            
            $result = $this->db->executeSQL("DELETE FROM agents WHERE id = $id");
            $this->system->getLogger()->info('Agent deleted', ['id' => $id]);
            return !isset($result[0]['error']);
        } catch (\Exception $e) {
            $this->system->getLogger()->error('Failed to delete agent', ['id' => $id, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    public function getById(int $id): ?array {
        try {
            $result = $this->db->executeSQL("SELECT * FROM agents WHERE id = $id");
            return $result[0]['data'][0] ?? null;
        } catch (\Exception $e) {
            $this->system->getLogger()->error('Failed to get agent', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }
}