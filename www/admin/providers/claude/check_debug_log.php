<?php
try {
    require_once __DIR__ . '/../../../src/bootstrap.php';
    header('Content-Type: text/plain');
} catch (Exception $e) {
    try {
        $logger = \ZeroAI\Core\Logger::getInstance();
        $logger->logClaude('Check debug log bootstrap failed: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    } catch (Exception $logError) {
        error_log('Check Debug Log Bootstrap Error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo 'System error';
    exit;
}

echo "=== CLAUDE DEBUG LOG ===\n\n";

if (file_exists('/tmp/claude_debug.log')) {
    echo file_get_contents('/tmp/claude_debug.log');
} else {
    echo "Debug log not found at /tmp/claude_debug.log\n";
    echo "Checking alternative locations...\n\n";
    
    // Check other possible locations
    $locations = [
        '/var/log/claude_debug.log',
        '/app/logs/claude_debug.log',
        '/app/claude_debug.log'
    ];
    
    foreach ($locations as $location) {
        if (file_exists($location)) {
            echo "Found log at: $location\n";
            echo file_get_contents($location);
            break;
        }
    }
}
?>
