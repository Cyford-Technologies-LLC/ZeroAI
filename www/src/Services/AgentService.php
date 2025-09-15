<?php

namespace ZeroAI\Services;

use ZeroAI\Models\Agent;

class AgentService {
    private $agent;
    
    public function __construct() {
        $this->agent = new Agent();
    }
    
    public function getAllAgents() {
        return $this->agent->getAll();
    }
    
    public function createAgent($data) {
        return $this->agent->create($data);
    }
    
    public function updateAgent($id, $data) {
        return $this->agent->update($id, $data);
    }
    
    public function deleteAgent($id) {
        return $this->agent->delete($id);
    }
    
    public function getAgentStats() {
        $agents = $this->getAllAgents();
        return [
            'total' => count($agents),
            'active' => count(array_filter($agents, fn($a) => $a['status'] === 'active')),
            'roles' => array_unique(array_column($agents, 'role'))
        ];
    }
}
