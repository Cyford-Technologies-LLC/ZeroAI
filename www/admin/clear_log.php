<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    return;
}

$input = json_decode(file_get_contents('php://input'), true);
$logType = \ZeroAI\Core\InputValidator::sanitize($input['log'] ?? '');

$logFiles = [
    'zeroai' => '/app/logs/errors.log',
    'nginx' => '/var/log/nginx/error.log',
    'php' => '/var/log/php8.1-fpm.log', 
    'claude' => '/app/logs/claude_commands.log'
];

if (!isset($logFiles[$logType])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid log type']);
    return;
}

$logFile = $logFiles[$logType];

// Validate file path
if (!\ZeroAI\Core\InputValidator::validatePath($logFile)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid file path']);
    return;
}

try {
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
        echo json_encode(['success' => true, 'message' => ucfirst($logType) . ' log cleared']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Log file not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
