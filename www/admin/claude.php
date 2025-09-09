<?php 
$pageTitle = 'Cloud AI Assistant - ZeroAI';
$currentPage = 'claude';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../api/python_cloud_bridge.php';

$cloudBridge = new PythonCloudBridge();
$currentConfig = $cloudBridge->getCurrentCloudConfig();

// Handle provider setup
if ($_POST['action'] ?? '' === 'setup_provider') {
    $provider = $_POST['provider'];
    $config = ['api_key' => $_POST['api_key']];
    $setupResult = $cloudBridge->updateCloudProvider($provider, $config);
    $currentConfig = $cloudBridge->getCurrentCloudConfig(); // Refresh config
}

// Handle chat with cloud AI
if ($_POST['action'] ?? '' === 'chat_cloud') {
    $provider = $_POST['provider'] ?? $currentConfig['provider'] ?? 'anthropic';
    $response = $cloudBridge->chatWithCloudAgent($provider, $_POST['message'], [
        'user' => $_SESSION['admin_user'],
        'system_status' => 'online',
        'active_agents' => 3,
        'current_provider' => $provider
    ]);
    
    if ($response['success']) {
        $cloudResponse = $response['response'];
        $usedModel = $response['model'];
    } else {
        $error = $response['error'];
    }
}

// Test connection
if ($_POST['action'] ?? '' === 'test_connection') {
    $provider = $_POST['provider'] ?? $currentConfig['provider'] ?? 'anthropic';
    $testResult = $cloudBridge->testCloudProvider($provider);
}
?>

<h1>Cloud AI Assistant</h1>

<div class="card">
    <h3>Current Cloud Provider: <?= ucfirst($currentConfig['provider'] ?? 'local') ?></h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">
        <div>OpenAI: <?= $currentConfig['has_openai_key'] ? '✅ Configured' : '❌ Not configured' ?></div>
        <div>Anthropic: <?= $currentConfig['has_anthropic_key'] ? '✅ Configured' : '❌ Not configured' ?></div>
        <div>Azure: <?= $currentConfig['has_azure_key'] ? '✅ Configured' : '❌ Not configured' ?></div>
        <div>Google: <?= $currentConfig['has_google_key'] ? '✅ Configured' : '❌ Not configured' ?></div>
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
</div>

<div class="card">
    <h3>Chat with Claude</h3>
    <p>Ask Claude to help optimize your ZeroAI system, analyze agent performance, or assist with development.</p>
    
    <?php if (isset($error)): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="action" value="chat_claude">
        <textarea name="message" placeholder="Ask Claude about ZeroAI optimization, agent improvements, or development help..." rows="4" required></textarea>
        <button type="submit" class="btn-success">Ask Claude</button>
    </form>
    
    <?php if (isset($claudeResponse)): ?>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #007bff;">
            <h4>Claude's Response:</h4>
            <div style="white-space: pre-wrap; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                <?= htmlspecialchars($claudeResponse) ?>
            </div>
            <small style="color: #666; margin-top: 10px; display: block;">
                Tokens used: <?= $tokensUsed ?> | Model: claude-3-5-sonnet-20241022
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