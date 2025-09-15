<?php
require_once 'includes/autoload.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

$queue = \ZeroAI\Core\QueueManager::getInstance();
$success = false;

switch ($action) {
    case 'clear':
        $success = $queue->clear();
        break;
    case 'test':
        $success = $queue->push('test_table', [
            'name' => 'Test Job',
            'created_at' => date('Y-m-d H:i:s'),
            'data' => json_encode(['test' => true])
        ]);
        break;
}

echo json_encode(['success' => $success]);


