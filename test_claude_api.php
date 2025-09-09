<?php
// Test Claude API key loading

echo "Testing Claude API setup:\n\n";

// Test .env file loading
if (file_exists('/app/.env')) {
    echo "✓ /app/.env exists\n";
    $lines = file('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $hasAnthropicKey = false;
    foreach ($lines as $line) {
        if (strpos($line, 'ANTHROPIC_API_KEY') !== false) {
            $hasAnthropicKey = true;
            echo "✓ ANTHROPIC_API_KEY found in .env\n";
            break;
        }
    }
    if (!$hasAnthropicKey) {
        echo "✗ ANTHROPIC_API_KEY not found in .env\n";
    }
} else {
    echo "✗ /app/.env not found\n";
}

// Test environment variable
$apiKey = getenv('ANTHROPIC_API_KEY');
if ($apiKey) {
    echo "✓ ANTHROPIC_API_KEY loaded: " . substr($apiKey, 0, 10) . "...\n";
} else {
    echo "✗ ANTHROPIC_API_KEY not in environment\n";
}

// Test manual .env parsing
if (file_exists('.env')) {
    echo "✓ Local .env exists\n";
    $content = file_get_contents('.env');
    if (strpos($content, 'ANTHROPIC_API_KEY') !== false) {
        echo "✓ ANTHROPIC_API_KEY found in local .env\n";
    } else {
        echo "✗ ANTHROPIC_API_KEY not found in local .env\n";
    }
} else {
    echo "✗ Local .env not found\n";
}

// Test Claude integration class
require_once 'www/api/claude_integration.php';
try {
    $claude = new ClaudeIntegration();
    $test = $claude->testConnection();
    if ($test['success']) {
        echo "✓ Claude API connection works\n";
    } else {
        echo "✗ Claude API connection failed: " . $test['error'] . "\n";
    }
} catch (Exception $e) {
    echo "✗ Claude integration error: " . $e->getMessage() . "\n";
}
?>