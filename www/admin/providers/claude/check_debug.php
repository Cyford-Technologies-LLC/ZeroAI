<?php
try {
    require_once __DIR__ . '/../../../src/bootstrap.php';
    header('Content-Type: text/plain');
} catch (Exception $e) {
    try {
        $logger = \ZeroAI\Core\Logger::getInstance();
        $logger->logClaude('Check debug bootstrap failed: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    } catch (Exception $logError) {
        error_log('Check Debug Bootstrap Error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo 'System error';
    exit;
}
echo "=== CLAUDE DEBUG LOG ===\n\n";
if (file_exists('/app/logs/claude_debug.log')) {
    $lines = file('/app/logs/claude_debug.log');
    $recent = array_slice($lines, -20);
    foreach ($recent as $line) {
        echo $line;
    }
} else {
    echo "No debug log found\n";
}
?>