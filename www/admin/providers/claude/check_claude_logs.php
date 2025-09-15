<?php
try {
    require_once __DIR__ . '/../../../src/bootstrap.php';
    header('Content-Type: text/plain');
} catch (Exception $e) {
    try {
        $logger = \ZeroAI\Core\Logger::getInstance();
        $logger->logClaude('Check Claude logs bootstrap failed: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    } catch (Exception $logError) {
        error_log('Check Claude Logs Bootstrap Error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo 'System error';
    exit;
}

echo "=== CLAUDE DEBUG LOGS ===\n\n";

$errorLog = '/var/log/nginx/error.log';
if (file_exists($errorLog)) {
    $lines = file($errorLog, FILE_IGNORE_NEW_LINES);
    $debugLines = array_filter($lines, function($line) {
        return strpos($line, 'Claude History Debug') !== false || 
               strpos($line, 'Recent History Count') !== false ||
               strpos($line, 'Processed message') !== false ||
               strpos($line, 'Final messages to Claude') !== false;
    });
    
    $debugLines = array_slice($debugLines, -10);
    
    foreach ($debugLines as $line) {
        echo $line . "\n";
    }
} else {
    echo "Error log not found\n";
}

echo "\n=== CHECKING CLAUDE INTEGRATION ===\n";

// Test the actual conversation history processing
$testHistory = [
    ['sender' => 'You', 'message' => 'Hello Claude', 'type' => 'user'],
    ['sender' => 'Claude', 'message' => 'Hello! How can I help?', 'type' => 'claude'],
    ['sender' => 'You', 'message' => 'Remember this task: optimize the system', 'type' => 'user']
];

require_once __DIR__ . '/../../api/claude_integration.php';

$claude = new ClaudeIntegration('test-key');

// Use reflection to test the private methods
$reflection = new ReflectionClass($claude);
$isClaudeMethod = $reflection->getMethod('isClaudeMessage');
$isClaudeMethod->setAccessible(true);
$isUserMethod = $reflection->getMethod('isUserMessage');
$isUserMethod->setAccessible(true);

echo "\nTesting sender detection:\n";
foreach ($testHistory as $item) {
    $sender = $item['sender'];
    $isClaude = $isClaudeMethod->invoke($claude, $sender);
    $isUser = $isUserMethod->invoke($claude, $sender);
    echo "Sender: '$sender' -> Claude: " . ($isClaude ? 'YES' : 'NO') . ", User: " . ($isUser ? 'YES' : 'NO') . "\n";
}
?>