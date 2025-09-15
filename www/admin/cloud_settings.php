<?php 
require_once __DIR__ . '/../src/bootstrap_secure.php';
use ZeroAI\Core\InputValidator;
use ZeroAI\Core\SecurityException;
use ZeroAI\Core\FileSecurityException;

// Load environment variables securely
$envPath = '/app/.env';
if (InputValidator::validatePath($envPath) && file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
                list($key, $value) = explode('=', $line, 2);
                $key = InputValidator::sanitize(trim($key));
                $value = trim($value);
                $_ENV[$key] = $value;
                putenv($key . '=' . $value);
            }
        }
    }
}

$pageTitle = 'Cloud AI Settings - ZeroAI';
$currentPage = 'cloud_settings';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/autoload.php';

$settingsService = new \ZeroAI\Services\SettingsService();
$currentConfig = $settingsService->getCloudConfig();

// Handle provider setup with security validation
if ($_POST['action'] ?? '' === 'setup_provider') {
    try {
        // Validate CSRF token
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!InputValidator::validateCSRFToken($csrfToken)) {
            throw new SecurityException('Invalid CSRF token');
        }
        
        $provider = InputValidator::sanitize($_POST['provider'] ?? 'anthropic');
        $apiKey = $_POST['api_key'] ?? '';
        
        // Validate provider
        $allowedProviders = ['anthropic', 'openai', 'azure', 'google'];
        if (!in_array($provider, $allowedProviders)) {
            throw new SecurityException('Invalid provider');
        }
        
        // Validate API key
        if (!InputValidator::validateApiKey($apiKey)) {
            throw new SecurityException('Invalid API key format');
        }
        
        // Secure file operations
        $envFile = '/app/.env';
        if (!InputValidator::validatePath($envFile, true)) {
            throw new FileSecurityException('Invalid environment file path');
        }
        
        $envContent = file_get_contents($envFile);
        if ($envContent === false) {
            throw new FileSecurityException('Cannot read environment file');
        }
        
        $keyName = strtoupper($provider) . '_API_KEY';
        
        if (strpos($envContent, $keyName) !== false) {
            $envContent = preg_replace('/^' . preg_quote($keyName) . '=.*/m', $keyName . '=' . $apiKey, $envContent);
        } else {
            $envContent .= "\n# Cloud AI Configuration\n" . $keyName . '=' . $apiKey . "\n";
        }
        
        if (file_put_contents($envFile, $envContent) === false) {
            throw new FileSecurityException('Cannot write to environment file');
        }
        
        // Update settings.yaml securely
        $yamlFile = '/app/config/settings.yaml';
        if (InputValidator::validatePath($yamlFile, true) && file_exists($yamlFile)) {
            $yamlContent = file_get_contents($yamlFile);
            if ($yamlContent !== false) {
                $yamlContent = preg_replace('/provider:\s*"?local"?/', 'provider: "' . $provider . '"', $yamlContent);
                file_put_contents($yamlFile, $yamlContent);
            }
        }
        
        $setupResult = ['success' => true, 'provider' => $provider];
        
    } catch (SecurityException $e) {
        $setupResult = ['success' => false, 'error' => $e->getMessage()];
    } catch (Exception $e) {
        $setupResult = ['success' => false, 'error' => 'Configuration failed'];
    }
    
    $currentConfig = $settingsService->getCloudConfig();
}

// Test connection with security measures
if ($_POST['action'] ?? '' === 'test_connection') {
    try {
        // Validate CSRF token
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!InputValidator::validateCSRFToken($csrfToken)) {
            throw new SecurityException('Invalid CSRF token');
        }
        
        $provider = InputValidator::sanitize($_POST['provider'] ?? 'anthropic');
        
        // Validate provider
        $allowedProviders = ['anthropic', 'openai'];
        if (!in_array($provider, $allowedProviders)) {
            throw new SecurityException('Invalid provider');
        }
        
        $envPath = '/app/.env';
        if (!InputValidator::validatePath($envPath)) {
            throw new FileSecurityException('Invalid environment file path');
        }
        
        $envContent = file_get_contents($envPath);
        if ($envContent === false) {
            throw new FileSecurityException('Cannot read environment file');
        }
        
        preg_match('/ANTHROPIC_API_KEY=(.+)/', $envContent, $matches);
        $apiKey = isset($matches[1]) ? trim($matches[1]) : '';
        
        if (!InputValidator::validateApiKey($apiKey)) {
            throw new SecurityException('Invalid or missing API key');
        }
        
        // Use proc_open for safer command execution
        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"]
        ];
        
        $pythonScript = 'from crewai import LLM; llm = LLM(model="anthropic/claude-sonnet-4-20250514"); response = llm.call("Test connection - respond with: Connection successful"); print(response)';
        
        $process = proc_open(
            '/app/venv/bin/python -c ' . escapeshellarg($pythonScript),
            $descriptorspec,
            $pipes,
            '/app',
            ['ANTHROPIC_API_KEY' => $apiKey, 'HOME' => '/tmp']
        );
        
        if (is_resource($process)) {
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            
            if ($output && !strpos($output, 'Error') && !strpos($output, 'Traceback') && empty($error)) {
                $testResult = [
                    'success' => true,
                    'provider' => $provider,
                    'model' => 'claude-sonnet-4-20250514',
                    'response' => InputValidator::sanitize(trim($output))
                ];
            } else {
                $testResult = [
                    'success' => false,
                    'provider' => $provider,
                    'error' => 'Connection test failed'
                ];
            }
        } else {
            $testResult = [
                'success' => false,
                'provider' => $provider,
                'error' => 'Cannot execute test command'
            ];
        }
        
    } catch (SecurityException $e) {
        $testResult = [
            'success' => false,
            'provider' => $provider ?? 'unknown',
            'error' => $e->getMessage()
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
    
    <p><strong>Active Provider:</strong> <?= InputValidator::sanitizeForOutput(ucfirst($currentConfig['provider'] ?? 'local')) ?></p>
    
    <?php if (isset($setupResult)): ?>
        <div class="message <?= $setupResult['success'] ? '' : 'error' ?>">
            <?php if ($setupResult['success']): ?>
                ✅ <?= InputValidator::sanitizeForOutput(ucfirst($setupResult['provider'])) ?> configured successfully!
            <?php else: ?>
                ❌ Setup failed: <?= InputValidator::sanitizeForOutput($setupResult['error']) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Setup Cloud Provider</h3>
    <form method="POST">
        <input type="hidden" name="action" value="setup_provider">
        <input type="hidden" name="csrf_token" value="<?= InputValidator::generateCSRFToken() ?>">
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
        <input type="hidden" name="csrf_token" value="<?= InputValidator::generateCSRFToken() ?>">
        <select name="provider">
            <option value="anthropic">Claude (Anthropic)</option>
            <option value="openai">GPT-4 (OpenAI)</option>
        </select>
        <button type="submit" class="btn-primary">Test Connection</button>
    </form>
    
    <?php if (isset($testResult)): ?>
        <div class="message <?= $testResult['success'] ? '' : 'error' ?>">
            <?php if ($testResult['success']): ?>
                ✅ <?= InputValidator::sanitizeForOutput(ucfirst($testResult['provider'])) ?> connected successfully! 
                <br>Model: <?= InputValidator::sanitizeForOutput($testResult['model']) ?>
                <br>Response: <?= InputValidator::sanitizeForOutput(substr($testResult['response'], 0, 100)) ?>...
            <?php else: ?>
                ❌ Connection failed: <?= InputValidator::sanitizeForOutput($testResult['error']) ?>
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


