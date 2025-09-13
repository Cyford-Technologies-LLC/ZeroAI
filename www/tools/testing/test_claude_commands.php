<?php
require_once __DIR__ . '/../../bootstrap.php';

echo "<h1>Claude Commands Test</h1>";

// Test ClaudeCommands class
try {
    $commands = new \ZeroAI\Providers\AI\Claude\ClaudeCommands();
    
    echo "<h2>Testing @file command</h2>";
    $message = "@file .env";
    $commands->processFileCommands($message);
    echo "<pre>" . htmlspecialchars($message) . "</pre>";
    
    echo "<h2>Testing @list command</h2>";
    $message = "@list config";
    $commands->processFileCommands($message);
    echo "<pre>" . htmlspecialchars($message) . "</pre>";
    
    echo "<h2>Testing @agents command</h2>";
    $message = "@agents";
    $commands->processClaudeCommands($message);
    echo "<pre>" . htmlspecialchars($message) . "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>