<?php 
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

// Handle chat with cloud AI - Direct implementation
if ($_POST['action'] ?? '' === 'chat_cloud') {
    $message = $_POST['message'] ?? '';
    // Try multiple ways to get the API key
    $apiKey = getenv('ANTHROPIC_API_KEY') ?: $_ENV['ANTHROPIC_API_KEY'] ?? null;
    
    // If not found, read directly from .env file
    if (!$apiKey) {
        $envContent = file_get_contents('/app/.env');
        if (preg_match('/ANTHROPIC_API_KEY=(.+)/', $envContent, $matches)) {
            $apiKey = trim($matches[1]);
        }
    }
    
    if ($apiKey && $message) {
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01'
        ];
        
        $data = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 4000,
            'system' => 'You are Claude, integrated into ZeroAI - a zero-cost AI workforce platform. Help optimize ZeroAI configurations, analyze agent performance, and provide development assistance.',
            'messages' => [[
                'role' => 'user',
                'content' => $message
            ]]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if ($result && isset($result['content'][0]['text'])) {
                $cloudResponse = $result['content'][0]['text'];
                $tokensUsed = ($result['usage']['input_tokens'] ?? 0) + ($result['usage']['output_tokens'] ?? 0);
                $usedModel = 'claude-3-5-sonnet-20241022';
            } else {
                $error = 'Invalid response from Claude API';
            }
        } else {
            $error = 'Claude API error: HTTP ' . $httpCode;
        }
    } else {
        $error = $apiKey ? 'Message required' : 'Claude API key not configured';
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
    <h3>Chat with Cloud AI</h3>
    <p>Ask your cloud AI to help optimize your ZeroAI system, analyze agent performance, or assist with development.</p>
    
    <?php if (isset($error)): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="action" value="chat_claude">
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