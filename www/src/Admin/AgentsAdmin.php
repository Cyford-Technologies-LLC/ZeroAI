<?php

namespace ZeroAI\Admin;

class AgentsAdmin extends BaseAdmin {
    private $agentDB;
    
    protected function handleRequest() {
        require_once __DIR__ . '/../../api/agent_db.php';
        $this->agentDB = new \AgentDB();
        
        if ($_POST['action'] ?? '' === 'update_agent') {
            $this->updateAgent();
        }
        
        $this->data['agents'] = $this->agentDB->getAllAgents();
    }
    
    private function updateAgent() {
        $id = $_POST['agent_id'];
        $data = [
            'role' => $_POST['role'],
            'goal' => $_POST['goal'],
            'backstory' => $_POST['backstory'],
            'status' => $_POST['status']
        ];
        
        $this->agentDB->updateAgent($id, $data);
        $this->data['message'] = 'Agent updated successfully!';
    }
    
    protected function renderContent() {
        ?>
        <h1>Agent Management</h1>
        
        <?php if (isset($this->data['message'])): ?>
            <div class="message"><?= htmlspecialchars($this->data['message']) ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h3>Active Agents</h3>
            <table>
                <tr>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($this->data['agents'] as $agent): ?>
                <tr>
                    <td><?= htmlspecialchars($agent['name']) ?></td>
                    <td><?= htmlspecialchars($agent['role']) ?></td>
                    <td><?= htmlspecialchars($agent['status']) ?></td>
                    <td>
                        <button onclick="editAgent(<?= $agent['id'] ?>)">Edit</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }
}


