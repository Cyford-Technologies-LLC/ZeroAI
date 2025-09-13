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

$pageTitle = 'Cloud AI Settings - ZeroAI';
$currentPage = 'cloud_settings';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../api/python_cloud_bridge.php';

$cloudBridge = new PythonCloudBridge();
$currentConfig = $cloudBridge->getCurrentCloudConfig();

// Handle provider setup
if ($_POST['action'] ?? '' === 'setup_provider') {
    $provider = $_POST['provider'] ?? 'anthropic';
    $apiKey = $_POST['api_key'] ?? '';
    
    if ($apiKey) {
        try {
            $envFile = '/app/.env';
            $envContent = file_get_contents($envFile);
            
            $keyName = strtoupper($provider) . '_API_KEY';
            
            if (strpos($envContent, $keyName) !== false) {
                $envContent = preg_replace('/^' . $keyName . '=.*/m', $keyName . '=' . $apiKey, $envContent);
            } else {
                $envContent .= "\n# Cloud AI Configuration\n" . $keyName . '=' . $apiKey . "\n";
            }
            
            file_put_contents($envFile, $envContent);
            
            // Update settings.yaml
            $yamlFile = '/app/config/settings.yaml';
            $yamlContent = file_get_contents($yamlFile);
            $yamlContent = preg_replace('/provider:\s*"?local"?/', 'provider: "' . $provider . '"', $yamlContent);
            file_put_contents($yamlFile, $yamlContent);
            
            $setupResult = ['success' => true, 'provider' => $provider];
        } catch (Exception $e) {
            $setupResult = ['success' => false, 'error' => $e->getMessage()];
        }
    } else {
        $setupResult = ['success' => false, 'error' => 'API key required'];
    }
    
    $currentConfig = $cloudBridge->getCurrentCloudConfig();
}

// Test connection
if ($_POST['action'] ?? '' === 'test_connection') {
    $provider = $_POST['provider'] ?? 'anthropic';
    
    $envContent = file_get_contents('/app/.env');
    preg_match('/ANTHROPIC_API_KEY=(.+)/', $envContent, $matches);
    $apiKey = isset($matches[1]) ? trim($matches[1]) : '';
    
    if ($apiKey) {
        $pythonCmd = 'export HOME=/tmp && export ANTHROPIC_API_KEY=' . escapeshellarg($apiKey) . ' && /app/venv/bin/python -c "
            from crewai import LLM; 
            llm = LLM(model=\"anthropic/claude-sonnet-4-20250514\"); 
            response = llm.call(\"Test connection - respond with: Connection successful\"); 
            print(response)
            "';
        
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

<h1>Cloud AI Provider Settings</h1>

<div class="card">
    <h3>Current Configuration</h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">
        <div>OpenAI: <?= isset($currentConfig['has_openai_key']) && $currentConfig['has_openai_key'] ? '✅ Configured' : '❌ Not configured' ?></div>
        <div>Anthropic: <?= isset($currentConfig['has_anthropic_key']) && $currentConfig['has_anthropic_key'] ? '✅ Configured' : '❌ Not configured' ?></div>
        <div>Azure: <?= isset($currentConfig['has_azure_key']) && $currentConfig['has_azure_key'] ? '✅ Configured' : '❌ Not configured' ?></div>
        <div>Google: <?= isset($currentConfig['has_google_key']) && $currentConfig['has_google_key'] ? '✅ Configured' : '❌ Not configured' ?></div>
    </div>
    
    <p><strong>Active Provider:</strong> <?= ucfirst($currentConfig['provider'] ?? 'local') ?></p>
    
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
        <div style="display: grid; grid-template-columns: 150px 300px 1fr; gap: 10px; align-items: center;">
            <select name="provider">
                <option value="anthropic">Claude (Anthropic)</option>
                <option value="openai">GPT-4 (OpenAI)</option>
                <option value="azure">Azure OpenAI</option>
                <option value="google">Google AI</option>
            </select>
            <input type="password" name="api_key" placeholder="Enter API Key" required>
            <button type="submit" class="btn-success">Setup Provider</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>Test Connection</h3>
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
</div>

<div class="card">
    <h3>Provider Information</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <div>
            <h4>🤖 Anthropic Claude</h4>
            <p>Advanced reasoning and code analysis</p>
            <ul>
                <li>Claude 3.5 Sonnet</li>
                <li>200K context window</li>
                <li>Best for complex reasoning</li>
            </ul>
        </div>
        <div>
            <h4>🧠 OpenAI GPT-4</h4>
            <p>General purpose AI with broad knowledge</p>
            <ul>
                <li>GPT-4 Turbo</li>
                <li>128K context window</li>
                <li>Fast and reliable</li>
            </ul>
        </div>
        <div>
            <h4>☁️ Azure OpenAI</h4>
            <p>Enterprise-grade OpenAI models</p>
            <ul>
                <li>Enhanced security</li>
                <li>Regional deployment</li>
                <li>SLA guarantees</li>
            </ul>
        </div>
        <div>
            <h4>🔍 Google AI</h4>
            <p>Google's Gemini models</p>
            <ul>
                <li>Gemini Pro</li>
                <li>Multimodal capabilities</li>
                <li>Fast inference</li>
            </ul>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>