<?php

namespace ZeroAI\Admin;

class ClaudeSettingsAdmin extends BaseAdmin {
    private $claudeConfig = [];
    
    protected function handleRequest() {
        if ($_POST['action'] ?? '' === 'update_claude_config') {
            $this->updateClaudeConfig();
        }
        $this->loadClaudeConfig();
    }
    
    private function updateClaudeConfig() {
        $config = [
            'role' => $_POST['claude_role'] ?? '',
            'goal' => $_POST['claude_goal'] ?? '',
            'backstory' => $_POST['claude_backstory'] ?? '',
            'supervision_level' => $_POST['supervision_level'] ?? 'moderate',
            'focus_areas' => $_POST['focus_areas'] ?? [],
            'supervisor_model' => $_POST['supervisor_model'] ?? 'claude-sonnet-4-20250514'
        ];
        
        require_once __DIR__ . '/../../api/agent_db.php';
        $agentDB = new \AgentDB();
        
        $agents = $agentDB->getAllAgents();
        $claudeAgent = null;
        foreach ($agents as $agent) {
            if ($agent['name'] === 'Claude AI Assistant') {
                $claudeAgent = $agent;
                break;
            }
        }
        
        if ($claudeAgent) {
            $agentDB->updateAgent($claudeAgent['id'], [
                'name' => 'Claude AI Assistant',
                'role' => $config['role'],
                'goal' => $config['goal'],
                'backstory' => $config['backstory'],
                'status' => 'active',
                'llm_model' => 'claude'
            ]);
            $this->data['message'] = 'Claude configuration updated successfully!';
        } else {
            $agentDB->createAgent([
                'name' => 'Claude AI Assistant',
                'role' => $config['role'],
                'goal' => $config['goal'],
                'backstory' => $config['backstory'],
                'status' => 'active',
                'llm_model' => 'claude',
                'is_core' => 1
            ]);
            $this->data['message'] = 'Claude agent created and configured successfully!';
        }
        
        $this->saveConfigFile($config);
    }
    
    private function saveConfigFile($config) {
        $configFile = '/app/config/claude_config.yaml';
        $yamlContent = "# Claude Configuration\n";
        $yamlContent .= "role: \"" . str_replace('"', '\\"', $config['role']) . "\"\n";
        $yamlContent .= "goal: \"" . str_replace('"', '\\"', $config['goal']) . "\"\n";
        $yamlContent .= "backstory: \"" . str_replace('"', '\\"', $config['backstory']) . "\"\n";
        $yamlContent .= "supervision_level: \"" . $config['supervision_level'] . "\"\n";
        $yamlContent .= "supervisor_model: \"" . $config['supervisor_model'] . "\"\n";
        $yamlContent .= "focus_areas:\n";
        foreach ($config['focus_areas'] as $area) {
            $yamlContent .= "  - \"$area\"\n";
        }
        file_put_contents($configFile, $yamlContent);
    }
    
    private function loadClaudeConfig() {
        if (file_exists('/app/config/claude_config.yaml')) {
            $lines = file('/app/config/claude_config.yaml', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, ': ') !== false && !str_starts_with($line, '#')) {
                    list($key, $value) = explode(': ', $line, 2);
                    $this->claudeConfig[trim($key)] = trim($value, '"');
                }
            }
        }
    }
    
    protected function renderContent() {
        $hasAnthropicKey = !empty($_ENV['ANTHROPIC_API_KEY']);
        ?>
        <h1>Claude AI Configuration</h1>
        
        <?php if (!$hasAnthropicKey): ?>
        <div class="card" style="border-left: 4px solid #ffc107;">
            <h3>⚠️ Anthropic API Key Required</h3>
            <p>Claude requires an Anthropic API key to function. Please configure it in Cloud Provider Settings first.</p>
            <a href="/admin/cloud_settings" class="btn-warning">Configure API Key</a>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h3>🧠 Claude Personality & Role</h3>
            <p>Customize Claude's role, goal, and backstory for your ZeroAI system.</p>
            
            <?php if (isset($this->data['message'])): ?>
                <div class="message"><?= htmlspecialchars($this->data['message']) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_claude_config">
                
                <label><strong>Role:</strong></label>
                <input type="text" name="claude_role" value="<?= htmlspecialchars($this->claudeConfig['role'] ?? 'Senior AI Architect & Code Review Specialist') ?>" required>
                
                <label><strong>Goal:</strong></label>
                <textarea name="claude_goal" rows="3" required><?= htmlspecialchars($this->claudeConfig['goal'] ?? 'Provide expert code review, architectural guidance, and strategic optimization recommendations to enhance ZeroAI system performance and development quality.') ?></textarea>
                
                <label><strong>Backstory:</strong></label>
                <textarea name="claude_backstory" rows="4" required><?= htmlspecialchars($this->claudeConfig['backstory'] ?? 'I am Claude, an advanced AI assistant created by Anthropic. I specialize in software architecture, code optimization, and strategic technical guidance.') ?></textarea>
                
                <button type="submit" class="btn-success">Update Claude Configuration</button>
            </form>
        </div>
        <?php
    }
}


