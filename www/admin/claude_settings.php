<?php 
// Load environment variables
if (file_exists('/app/.env')) {
    $lines = file('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

$pageTitle = 'Claude AI Settings - ZeroAI';
$currentPage = 'claude_settings';
include __DIR__ . '/includes/header.php';

// Handle Claude configuration update
if ($_POST['action'] ?? '' === 'update_claude_config') {
    $claudeConfig = [
        'role' => $_POST['claude_role'] ?? '',
        'goal' => $_POST['claude_goal'] ?? '',
        'backstory' => $_POST['claude_backstory'] ?? '',
        'supervision_level' => $_POST['supervision_level'] ?? 'moderate',
        'focus_areas' => $_POST['focus_areas'] ?? [],
        'supervisor_model' => $_POST['supervisor_model'] ?? 'claude-sonnet-4-20250514'
    ];
    
    // Save to database
    require_once __DIR__ . '/includes/autoload.php';
    $agentService = new \ZeroAI\Services\AgentService();
    
    // Update Claude agent in database
    $agents = $agentService->getAllAgents();
    $claudeAgent = null;
    foreach ($agents as $agent) {
        if ($agent['name'] === 'Claude AI Assistant') {
            $claudeAgent = $agent;
            break;
        }
    }
    
    if ($claudeAgent) {
        $agentService->updateAgent($claudeAgent['id'], [
            'name' => 'Claude AI Assistant',
            'role' => $claudeConfig['role'],
            'goal' => $claudeConfig['goal'],
            'backstory' => $claudeConfig['backstory'],
            'status' => 'active',
            'llm_model' => 'claude'
        ]);
        $configMessage = 'Claude configuration updated successfully!';
    } else {
        // Create Claude agent if it doesn't exist
        $agentService->createAgent([
            'name' => 'Claude AI Assistant',
            'role' => $claudeConfig['role'],
            'goal' => $claudeConfig['goal'],
            'backstory' => $claudeConfig['backstory'],
            'status' => 'active',
            'llm_model' => 'claude',
            'is_core' => 1
        ]);
        $configMessage = 'Claude agent created and configured successfully!';
    }
    
    // Save to config file for Python access
    $configFile = '/app/config/claude_config.yaml';
    $yamlContent = "# Claude Configuration\n";
    $yamlContent .= "role: \"" . str_replace('"', '\\"', $claudeConfig['role']) . "\"\n";
    $yamlContent .= "goal: \"" . str_replace('"', '\\"', $claudeConfig['goal']) . "\"\n";
    $yamlContent .= "backstory: \"" . str_replace('"', '\\"', $claudeConfig['backstory']) . "\"\n";
    $yamlContent .= "supervision_level: \"" . $claudeConfig['supervision_level'] . "\"\n";
    $yamlContent .= "supervisor_model: \"" . $claudeConfig['supervisor_model'] . "\"\n";
    $yamlContent .= "focus_areas:\n";
    foreach ($claudeConfig['focus_areas'] as $area) {
        $yamlContent .= "  - \"$area\"\n";
    }
    file_put_contents($configFile, $yamlContent);
}

// Load current Claude config
$claudeConfig = [];
if (file_exists('/app/config/claude_config.yaml')) {
    $lines = file('/app/config/claude_config.yaml', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, ': ') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode(': ', $line, 2);
            $claudeConfig[trim($key)] = trim($value, '"');
        }
    }
}

// Check if Claude is configured
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
    
    <?php if (isset($configMessage)): ?>
        <div class="message"><?= htmlspecialchars($configMessage) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="action" value="update_claude_config">
        
        <label><strong>Role:</strong></label>
        <input type="text" name="claude_role" value="<?= htmlspecialchars($claudeConfig['role'] ?? 'Senior AI Architect & Code Review Specialist') ?>" placeholder="Claude's role in your team" required>
        
        <label><strong>Goal:</strong></label>
        <textarea name="claude_goal" rows="3" placeholder="What should Claude focus on?" required><?= htmlspecialchars($claudeConfig['goal'] ?? 'Provide expert code review, architectural guidance, and strategic optimization recommendations to enhance ZeroAI system performance and development quality.') ?></textarea>
        
        <label><strong>Backstory:</strong></label>
        <textarea name="claude_backstory" rows="4" placeholder="Claude's background and expertise" required><?= htmlspecialchars($claudeConfig['backstory'] ?? 'I am Claude, an advanced AI assistant created by Anthropic. I specialize in software architecture, code optimization, and strategic technical guidance. I work alongside your ZeroAI development team to ensure high-quality deliverables, identify potential issues early, and provide insights that enhance overall project success. My expertise spans multiple programming languages, system design patterns, and best practices for scalable AI systems.') ?></textarea>
        
        <div style="margin: 15px 0;">
            <label><strong>Supervisor Model (for DevOps Crews):</strong></label>
            <select name="supervisor_model" style="width: 100%;">
                <option value="claude-sonnet-4-20250514" <?= ($claudeConfig['supervisor_model'] ?? 'claude-sonnet-4-20250514') === 'claude-sonnet-4-20250514' ? 'selected' : '' ?>>Claude Sonnet 4 (Most Advanced)</option>
                <option value="claude-3-opus-20240229" <?= ($claudeConfig['supervisor_model'] ?? '') === 'claude-3-opus-20240229' ? 'selected' : '' ?>>Claude 3 Opus</option>
                <option value="claude-3-5-sonnet-20241022" <?= ($claudeConfig['supervisor_model'] ?? '') === 'claude-3-5-sonnet-20241022' ? 'selected' : '' ?>>Claude 3.5 Sonnet</option>
                <option value="claude-3-5-haiku-20241022" <?= ($claudeConfig['supervisor_model'] ?? '') === 'claude-3-5-haiku-20241022' ? 'selected' : '' ?>>Claude 3.5 Haiku (Fastest)</option>
            </select>
            <small style="color: #666; display: block; margin-top: 5px;">This model will supervise your Mistral-Nemo agents in DevOps tasks</small>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0;">
            <div>
                <label><strong>Supervision Level:</strong></label>
                <select name="supervision_level">
                    <option value="light" <?= ($claudeConfig['supervision_level'] ?? 'moderate') === 'light' ? 'selected' : '' ?>>Light - Only major issues</option>
                    <option value="moderate" <?= ($claudeConfig['supervision_level'] ?? 'moderate') === 'moderate' ? 'selected' : '' ?>>Moderate - Code review + suggestions</option>
                    <option value="strict" <?= ($claudeConfig['supervision_level'] ?? 'moderate') === 'strict' ? 'selected' : '' ?>>Strict - Detailed analysis + optimization</option>
                </select>
            </div>
            <div>
                <label><strong>Focus Areas:</strong></label>
                <select name="focus_areas[]" multiple style="height: 80px;">
                    <option value="code_quality" <?= in_array('code_quality', $claudeConfig['focus_areas'] ?? ['code_quality', 'security']) ? 'selected' : '' ?>>Code Quality</option>
                    <option value="security" <?= in_array('security', $claudeConfig['focus_areas'] ?? ['code_quality', 'security']) ? 'selected' : '' ?>>Security</option>
                    <option value="performance" <?= in_array('performance', $claudeConfig['focus_areas'] ?? []) ? 'selected' : '' ?>>Performance</option>
                    <option value="architecture" <?= in_array('architecture', $claudeConfig['focus_areas'] ?? []) ? 'selected' : '' ?>>Architecture</option>
                    <option value="testing" <?= in_array('testing', $claudeConfig['focus_areas'] ?? []) ? 'selected' : '' ?>>Testing</option>
                    <option value="documentation" <?= in_array('documentation', $claudeConfig['focus_areas'] ?? []) ? 'selected' : '' ?>>Documentation</option>
                </select>
            </div>
        </div>
        
        <button type="submit" class="btn-success">Update Claude Configuration</button>
    </form>
</div>

<div class="card">
    <h3>🎯 Claude Capabilities</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        <div>
            <h4>💬 Direct Chat</h4>
            <p>Chat directly with Claude using your configured personality</p>
            <a href="/admin/chat?agent=claude" class="btn-primary">Start Chat</a>
        </div>
        <div>
            <h4>👥 Crew Supervision</h4>
            <p>Claude can supervise and coordinate your agent crews</p>
            <a href="/admin/crew_chat" class="btn-primary">Crew Management</a>
        </div>
        <div>
            <h4>📁 File Access</h4>
            <p>Claude can read and analyze your project files</p>
            <ul style="font-size: 12px; margin: 5px 0;">
                <li>@file path/to/file.py</li>
                <li>@list directory/</li>
                <li>@search pattern</li>
            </ul>
            <a href="#commands" class="btn-secondary" style="font-size: 11px;">View All Commands</a>
        </div>
        <div>
            <h4>🔍 Code Review</h4>
            <p>Automated code analysis and optimization suggestions</p>
            <a href="/admin/agents" class="btn-primary">View Agents</a>
        </div>
    </div>
</div>

<div class="card">
    <h3>⚙️ Advanced Settings</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <div>
            <h4>Model Configuration</h4>
            <p><strong>Model:</strong> Claude 3.5 Sonnet</p>
            <p><strong>Context Window:</strong> 200K tokens</p>
            <p><strong>Max Output:</strong> 4K tokens</p>
        </div>
        <div>
            <h4>Integration Status</h4>
            <p><strong>API Key:</strong> <?= $hasAnthropicKey ? '✅ Configured' : '❌ Missing' ?></p>
            <p><strong>Database:</strong> ✅ Connected</p>
            <p><strong>File Access:</strong> ✅ Enabled</p>
        </div>
        <div>
            <h4>Usage Guidelines</h4>
            <ul style="font-size: 14px;">
                <li>Claude works best with specific, detailed prompts</li>
                <li>Use @file commands to share code context</li>
                <li>Configure supervision level based on your needs</li>
                <li>Focus areas help Claude prioritize feedback</li>
            </ul>
        </div>
    </div>
</div>

<div class="card" id="commands">
    <h3>🔧 Claude Commands Reference</h3>
    <p>Use these commands when chatting with Claude to interact with your ZeroAI system:</p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <div>
            <h4>📁 File Access Commands</h4>
            <ul style="font-family: monospace; font-size: 12px; line-height: 1.6;">
                <li><strong>@file</strong> path/to/file.py - Read file contents</li>
                <li><strong>@read</strong> path/to/file.py - Read file contents (alias)</li>
                <li><strong>@list</strong> directory/ - List directory contents</li>
                <li><strong>@search</strong> pattern - Find files matching pattern</li>
            </ul>
        </div>
        
        <div>
            <h4>✏️ File Modification Commands</h4>
            <ul style="font-family: monospace; font-size: 12px; line-height: 1.6;">
                <li><strong>@create</strong> path/file.py ```code``` - Create new file</li>
                <li><strong>@edit</strong> path/file.py ```code``` - Replace file content</li>
                <li><strong>@append</strong> path/file.py ```code``` - Add to file</li>
                <li><strong>@delete</strong> path/file.py - Delete file</li>
            </ul>
        </div>
        
        <div>
            <h4>🤖 Agent Management Commands</h4>
            <ul style="font-family: monospace; font-size: 12px; line-height: 1.6;">
                <li><strong>@agents</strong> - List all agents</li>
                <li><strong>@update_agent</strong> 5 role="New Role" - Update agent</li>
                <li><strong>@crews</strong> - Show crew status</li>
                <li><strong>@analyze_crew</strong> task_id - Analyze crew execution</li>
            </ul>
        </div>
        
        <div>
            <h4>📈 Analysis Commands</h4>
            <ul style="font-family: monospace; font-size: 12px; line-height: 1.6;">
                <li><strong>@logs</strong> [days] [role] - Show crew logs</li>
                <li><strong>@optimize_agents</strong> - Analyze agent performance</li>
            </ul>
        </div>
    </div>
    
    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
        <h4>💡 Usage Examples</h4>
        <ul style="font-size: 13px; line-height: 1.6;">
            <li><code>@file src/main.py</code> - Share a Python file with Claude</li>
            <li><code>@list www/admin/</code> - Show Claude your admin directory structure</li>
            <li><code>@agents</code> - Get current agent status</li>
            <li><code>@create config/new_feature.yaml ```yaml content```</code> - Create a new config file</li>
            <li><code>@logs 7 developer</code> - Show last 7 days of developer agent logs</li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>