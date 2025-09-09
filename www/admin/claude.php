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

$pageTitle = 'Cloud AI Assistant - ZeroAI';
$currentPage = 'claude';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../api/python_cloud_bridge.php';

$cloudBridge = new PythonCloudBridge();
$currentConfig = $cloudBridge->getCurrentCloudConfig();

// Handle provider setup
if ($_POST['action'] ?? '' === 'setup_provider') {
    $provider = $_POST['provider'] ?? 'anthropic';
    $apiKey = $_POST['api_key'] ?? '';
    
    error_log("DEBUG: Provider setup - Provider: $provider, API Key: " . ($apiKey ? 'PROVIDED' : 'EMPTY'));
    
    if ($apiKey) {
        try {
            // Direct .env update
            $envFile = '/app/.env';
            $envContent = file_get_contents($envFile);
            
            $keyName = strtoupper($provider) . '_API_KEY';
            error_log("DEBUG: Key name: $keyName");
            
            if (strpos($envContent, $keyName) !== false) {
                // Update existing
                $envContent = preg_replace('/^' . $keyName . '=.*/m', $keyName . '=' . $apiKey, $envContent);
                error_log("DEBUG: Updated existing key");
            } else {
                // Add new
                $envContent .= "\n# Cloud AI Configuration\n" . $keyName . '=' . $apiKey . "\n";
                error_log("DEBUG: Added new key");
            }
            
            $writeResult = file_put_contents($envFile, $envContent);
            error_log("DEBUG: Write result: " . ($writeResult ? 'SUCCESS' : 'FAILED'));
            
            // Update settings.yaml
            $yamlFile = '/app/config/settings.yaml';
            $yamlContent = file_get_contents($yamlFile);
            $yamlContent = preg_replace('/provider:\s*"?local"?/', 'provider: "' . $provider . '"', $yamlContent);
            file_put_contents($yamlFile, $yamlContent);
            
            $setupResult = ['success' => true, 'provider' => $provider, 'debug' => 'Write result: ' . $writeResult];
        } catch (Exception $e) {
            error_log("DEBUG: Exception: " . $e->getMessage());
            $setupResult = ['success' => false, 'error' => $e->getMessage()];
        }
    } else {
        $setupResult = ['success' => false, 'error' => 'API key required'];
    }
    
    $currentConfig = $cloudBridge->getCurrentCloudConfig(); // Refresh config
}

// Handle Claude configuration update
if ($_POST['action'] ?? '' === 'update_claude_config') {
    $claudeConfig = [
        'role' => $_POST['claude_role'] ?? '',
        'goal' => $_POST['claude_goal'] ?? '',
        'backstory' => $_POST['claude_backstory'] ?? '',
        'supervision_level' => $_POST['supervision_level'] ?? 'moderate',
        'focus_areas' => $_POST['focus_areas'] ?? []
    ];
    
    // Save to database
    require_once __DIR__ . '/../api/agent_db.php';
    $agentDB = new AgentDB();
    
    // Update Claude agent in database
    $agents = $agentDB->getAllAgents();
    $claudeAgent = null;
    foreach ($agents as $agent) {
        if ($agent['role'] === 'Claude AI Assistant') {
            $claudeAgent = $agent;
            break;
        }
    }
    
    if ($claudeAgent) {
        $agentDB->updateAgent($claudeAgent['id'], [
            'name' => 'Claude AI Assistant',
            'role' => $claudeConfig['role'],
            'goal' => $claudeConfig['goal'],
            'backstory' => $claudeConfig['backstory'],
            'status' => 'active',
            'llm_model' => 'claude'
        ]);
        $configMessage = 'Claude configuration updated successfully!';
    } else {
        $configMessage = 'Claude agent not found in database. Please import agents first.';
    }
    
    // Also save to config file for Python access
    $configFile = '/app/config/claude_config.yaml';
    $yamlContent = "# Claude Configuration\n";
    $yamlContent .= "role: \"" . str_replace('"', '\\"', $claudeConfig['role']) . "\"\n";
    $yamlContent .= "goal: \"" . str_replace('"', '\\"', $claudeConfig['goal']) . "\"\n";
    $yamlContent .= "backstory: \"" . str_replace('"', '\\"', $claudeConfig['backstory']) . "\"\n";
    $yamlContent .= "supervision_level: \"" . $claudeConfig['supervision_level'] . "\"\n";
    $yamlContent .= "focus_areas:\n";
    foreach ($claudeConfig['focus_areas'] as $area) {
        $yamlContent .= "  - \"$area\"\n";
    }
    file_put_contents($configFile, $yamlContent);
}

// Load current Claude config
$claudeConfig = [];
if (file_exists('/app/config/claude_config.yaml')) {
    // Simple YAML parsing for our config
    $lines = file('/app/config/claude_config.yaml', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, ': ') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode(': ', $line, 2);
            $claudeConfig[trim($key)] = trim($value, '"');
        }
    }
}

// Handle chat with cloud AI - Use Python
if ($_POST['action'] ?? '' === 'chat_cloud') {
    $message = $_POST['message'] ?? '';
    
    if ($message) {
        // Read API key from .env file
        $envContent = file_get_contents('/app/.env');
        preg_match('/ANTHROPIC_API_KEY=(.+)/', $envContent, $matches);
        $apiKey = isset($matches[1]) ? trim($matches[1]) : '';
        
        if ($apiKey) {
            $escapedMessage = escapeshellarg($message);
            $pythonCmd = 'export HOME=/tmp && export ANTHROPIC_API_KEY=' . escapeshellarg($apiKey) . ' && /app/venv/bin/python -c "'
                . 'import sys; sys.path.append(\"/app\"); '
                . 'from crewai import LLM; '
                . 'llm = LLM(model=\"anthropic/claude-sonnet-4-20250514\"); '
                . 'response = llm.call(\"You are Claude integrated into ZeroAI. Help with: \" + ' . $escapedMessage . '); '
                . 'print(response)'
                . '"';
            
            $output = shell_exec($pythonCmd . ' 2>&1');
            
            if ($output && !strpos($output, 'Error') && !strpos($output, 'Traceback')) {
                $cloudResponse = trim($output);
                $usedModel = 'claude-sonnet-4-20250514';
                $tokensUsed = 'N/A';
            } else {
                $error = 'Python error: ' . $output;
            }
        } else {
            $error = 'API key not found in .env file';
        }
    } else {
        $error = 'Message required';
    }
}

// Test connection
if ($_POST['action'] ?? '' === 'test_connection') {
    $provider = $_POST['provider'] ?? 'anthropic';
    
    // Read API key from .env file
    $envContent = file_get_contents('/app/.env');
    preg_match('/ANTHROPIC_API_KEY=(.+)/', $envContent, $matches);
    $apiKey = isset($matches[1]) ? trim($matches[1]) : '';
    
    if ($apiKey) {
        $pythonCmd = 'export HOME=/tmp && export ANTHROPIC_API_KEY=' . escapeshellarg($apiKey) . ' && /app/venv/bin/python -c "'
            . 'from crewai import LLM; '
            . 'llm = LLM(model=\"anthropic/claude-sonnet-4-20250514\"); '
            . 'response = llm.call(\"Test connection - respond with: Connection successful\"); '
            . 'print(response)'
            . '"';
        
        $output = shell_exec($pythonCmd . ' 2>&1');
        
        if ($output && !strpos($output, 'Error') && !strpos($output, 'Traceback')) {
            $testResult = [
                'success' => true,
                'provider' => $provider,
                'model' => 'claude-sonnet-4-20250514',
                'response' => trim($output)
            ];
        } else {
            $testResult = [
                'success' => false,
                'provider' => $provider,
                'error' => 'Connection failed: ' . $output
            ];
        }
    } else {
        $testResult = [
            'success' => false,
            'provider' => $provider,
            'error' => 'API key not found in .env file'
        ];
    }
}
?>

<h1>Cloud AI Assistant</h1>

<div class="card">
    <h3>Current Cloud Provider: <?= ucfirst($currentConfig['provider'] ?? 'local') ?></h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">
        <div>OpenAI: <?= isset($currentConfig['has_openai_key']) && $currentConfig['has_openai_key'] ? '✅ Configured' : '❌ Not configured' ?></div>
        <div>Anthropic: <?= isset($currentConfig['has_anthropic_key']) && $currentConfig['has_anthropic_key'] ? '✅ Configured' : '❌ Not configured' ?></div>
        <div>Azure: <?= isset($currentConfig['has_azure_key']) && $currentConfig['has_azure_key'] ? '✅ Configured' : '❌ Not configured' ?></div>
        <div>Google: <?= isset($currentConfig['has_google_key']) && $currentConfig['has_google_key'] ? '✅ Configured' : '❌ Not configured' ?></div>
    </div>
    
    <form method="POST" style="display: inline;">
        <input type="hidden" name="action" value="test_connection">
        <select name="provider">
            <option value="anthropic">Claude (Anthropic)</option>
            <option value="openai">GPT-4 (OpenAI)</option>
        </select>
        <button type="submit" class="btn-primary">Test Connection</button>
    </form>
    
    <?php if (isset($testResult)): ?>
        <div class="message <?= $testResult['success'] ? '' : 'error' ?>">
            <?php if ($testResult['success']): ?>
                ✅ <?= ucfirst($testResult['provider']) ?> connected successfully! 
                <br>Model: <?= $testResult['model'] ?>
                <br>Response: <?= htmlspecialchars(substr($testResult['response'], 0, 100)) ?>...
            <?php else: ?>
                ❌ Connection failed: <?= htmlspecialchars($testResult['error']) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($setupResult)): ?>
        <div class="message <?= $setupResult['success'] ? '' : 'error' ?>">
            <?php if ($setupResult['success']): ?>
                ✅ <?= ucfirst($setupResult['provider']) ?> configured successfully!
            <?php else: ?>
                ❌ Setup failed: <?= htmlspecialchars($setupResult['error']) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($configMessage)): ?>
        <div class="message"><?= htmlspecialchars($configMessage) ?></div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Setup Cloud Provider</h3>
    <form method="POST">
        <input type="hidden" name="action" value="setup_provider">
        <select name="provider">
            <option value="anthropic">Claude (Anthropic)</option>
            <option value="openai">GPT-4 (OpenAI)</option>
        </select>
        <input type="password" name="api_key" placeholder="Enter API Key" required style="margin: 0 10px; width: 300px;">
        <button type="submit" class="btn-success">Setup Provider</button>
    </form>
</div>

<div class="card">
    <h3>🧠 Claude Configuration</h3>
    <p>Customize Claude's role, goal, and backstory for your ZeroAI system.</p>
    
    <form method="POST">
        <input type="hidden" name="action" value="update_claude_config">
        
        <label><strong>Role:</strong></label>
        <input type="text" name="claude_role" value="<?= htmlspecialchars($claudeConfig['role'] ?? 'Senior AI Architect & Code Review Specialist') ?>" placeholder="Claude's role in your team" required>
        
        <label><strong>Goal:</strong></label>
        <textarea name="claude_goal" rows="3" placeholder="What should Claude focus on?" required><?= htmlspecialchars($claudeConfig['goal'] ?? 'Provide expert code review, architectural guidance, and strategic optimization recommendations to enhance ZeroAI system performance and development quality.') ?></textarea>
        
        <label><strong>Backstory:</strong></label>
        <textarea name="claude_backstory" rows="4" placeholder="Claude's background and expertise" required><?= htmlspecialchars($claudeConfig['backstory'] ?? 'I am Claude, an advanced AI assistant created by Anthropic. I specialize in software architecture, code optimization, and strategic technical guidance. I work alongside your ZeroAI development team to ensure high-quality deliverables, identify potential issues early, and provide insights that enhance overall project success. My expertise spans multiple programming languages, system design patterns, and best practices for scalable AI systems.') ?></textarea>
        
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
                <select name="focus_areas" multiple style="height: 80px;">
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
    <h3>Chat with Cloud AI</h3>
    <p>Ask your cloud AI to help optimize your ZeroAI system, analyze agent performance, or assist with development.</p>
    
    <?php if (isset($error)): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="action" value="chat_cloud">
        <textarea name="message" placeholder="Ask Claude about ZeroAI optimization, agent improvements, or development help..." rows="4" required></textarea>
        <button type="submit" class="btn-success">Ask Claude</button>
    </form>
    
    <?php if (isset($cloudResponse)): ?>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #007bff;">
            <h4>Claude's Response:</h4>
            <div style="white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <?= htmlspecialchars($cloudResponse) ?>
            </div>
            <small style="color: #666; margin-top: 10px; display: block;">
                Tokens used: <?= $tokensUsed ?? 0 ?> | Model: <?= $usedModel ?? 'claude-3-5-sonnet-20241022' ?>
            </small>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Quick Actions</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_claude">
            <input type="hidden" name="message" value="Analyze my current ZeroAI configuration and suggest optimizations for better performance and cost efficiency.">
            <button type="submit" class="btn-primary" style="width: 100%;">Optimize My Config</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_claude">
            <input type="hidden" name="message" value="Review my agent performance data and suggest improvements for better task completion rates.">
            <button type="submit" class="btn-primary" style="width: 100%;">Analyze Agents</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_claude">
            <input type="hidden" name="message" value="Help me create a new specialized agent for my ZeroAI system. What should I consider?">
            <button type="submit" class="btn-primary" style="width: 100%;">Create New Agent</button>
        </form>
        
        <form method="POST">
            <input type="hidden" name="action" value="chat_claude">
            <input type="hidden" name="message" value="What are the best practices for scaling my ZeroAI workforce and managing multiple crews efficiently?">
            <button type="submit" class="btn-primary" style="width: 100%;">Scaling Advice</button>
        </form>
        
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>