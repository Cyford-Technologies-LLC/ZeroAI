<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$logType = $input['log'] ?? '';

$logFiles = [
    'nginx' => '/var/log/nginx/error.log',
    'php' => '/var/log/php8.1-fpm.log', 
    'claude' => '/app/logs/claude_commands.log'
];

if (!isset($logFiles[$logType])) {
    echo json_encode(['success' => false, 'error' => 'Invalid log type']);
    exit;
}

$logFile = $logFiles[$logType];

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