<?php
require_once __DIR__ . '/../../bootstrap.php';

echo "<h1>Command Output Test</h1>";

$message = "Test: @exec zeroai_api-test bash -c \"git status\"";
echo "<h2>Original:</h2><pre>" . htmlspecialchars($message) . "</pre>";

$commands = new \ZeroAI\Providers\AI\Claude\ClaudeCommands();
$commands->processClaudeCommands($message, 'claude', 'hybrid');

echo "<h2>After Processing:</h2><pre>" . htmlspecialchars($message) . "</pre>";

echo "<h2>Length Change:</h2>";
echo "Command outputs are " . (strlen($message) > 50 ? "being added" : "NOT being added");
?>