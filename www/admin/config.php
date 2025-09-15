<?php 
$pageTitle = 'Configuration - ZeroAI';
$currentPage = 'config';
include __DIR__ . '/includes/header.php';

// Handle config updates
if ($_POST['action'] ?? '' === 'update_config') {
    $configFile = '/app/config/settings.yaml';
    $envFile = '/app/.env';
    
    // Validate paths
    if (!\ZeroAI\Core\InputValidator::validatePath($configFile) || !\ZeroAI\Core\InputValidator::validatePath($envFile)) {
        $error = 'Invalid file path';
    } else {
        // Update YAML config
        if (isset($_POST['yaml_config'])) {
            file_put_contents($configFile, \ZeroAI\Core\InputValidator::sanitize($_POST['yaml_config']));
            $message = 'Configuration updated successfully';
        }
        
        // Update .env file
        if (isset($_POST['env_config'])) {
            file_put_contents($envFile, \ZeroAI\Core\InputValidator::sanitize($_POST['env_config']));
            $message = 'Environment variables updated successfully';
        }
    }
}

// Handle cloud provider setup
if ($_POST['action'] ?? '' === 'setup_claude') {
    $envFile = '/app/.env';
    $envContent = file_get_contents($envFile);
    
    // Add Claude API key
    if (!strpos($envContent, 'ANTHROPIC_API_KEY')) {
        $apiKey = \ZeroAI\Core\InputValidator::sanitize($_POST['claude_api_key']);
        $envContent .= "\n# Claude/Anthropic Configuration\n";
        $envContent .= "ANTHROPIC_API_KEY=" . $apiKey . "\n";
        $envContent .= "ANTHROPIC_MODEL=claude-3-5-sonnet-20241022\n";
        file_put_contents($envFile, $envContent);
    }
    
    // Update settings.yaml
    $configFile = '/app/config/settings.yaml';
    $yamlContent = file_get_contents($configFile);
    $yamlContent = preg_replace('/provider:\s*"local"/', 'provider: "anthropic"', $yamlContent);
    file_put_contents($configFile, $yamlContent);
    
    $message = 'Claude Sonnet configured successfully';
}

// Load current configs
$configContent = file_get_contents('/app/config/settings.yaml');
$envContent = file_get_contents('/app/.env');
?>

<h1>System Configuration</h1>

<?php if (isset($message)): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card">
    <h3>Quick Setup: Claude Sonnet Integration</h3>
    <p>Add Claude 3.5 Sonnet to help you and your AI agents with advanced reasoning and coding tasks.</p>
    
    <form method="POST">
        <input type="hidden" name="action" value="setup_claude">
        <input type="password" name="claude_api_key" placeholder="Enter your Anthropic API Key" required style="width: 400px;">
        <button type="submit" class="btn-success">Setup Claude Sonnet</button>
    </form>
    
    <p style="font-size: 0.9em; color: #666;">
        Get your API key from: <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>
    </p>
</div>

<div class="card">
    <h3>Configuration Files</h3>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div>
            <h4>settings.yaml</h4>
            <form method="POST">
                <input type="hidden" name="action" value="update_config">
                <textarea name="yaml_config" rows="20" style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars($configContent) ?></textarea>
                <button type="submit" class="btn-success">Update YAML Config</button>
            </form>
        </div>
        
        <div>
            <h4>.env Variables</h4>
            <form method="POST">
                <input type="hidden" name="action" value="update_config">
                <textarea name="env_config" rows="20" style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars($envContent) ?></textarea>
                <button type="submit" class="btn-success">Update Environment</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
