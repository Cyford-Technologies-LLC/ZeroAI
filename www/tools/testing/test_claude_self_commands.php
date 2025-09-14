<?php
require_once __DIR__ . '/../../bootstrap.php';

echo "<h1>Claude Self-Command Test</h1>";

// Simulate Claude writing her own command
$claudeProvider = new \ZeroAI\Providers\AI\Claude\ClaudeProvider();

$testMessage = "Let me check the config: @file config/settings.yaml";

echo "<h2>Original Message:</h2>";
echo "<pre>" . htmlspecialchars($testMessage) . "</pre>";

// Test command processing
$commands = new \ZeroAI\Providers\AI\Claude\ClaudeCommands();
$commands->processFileCommands($testMessage);

echo "<h2>After Command Processing:</h2>";
echo "<pre>" . htmlspecialchars($testMessage) . "</pre>";
?>