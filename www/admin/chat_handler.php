<?php
require_once __DIR__ . '/includes/autoload.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$selectedModel = $input['model'] ?? 'claude-3-5-sonnet-20241022';
$conversationHistory = $input['history'] ?? [];

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Message required']);
    exit;
}

try {
    $claudeProvider = new \ZeroAI\Providers\AI\Claude\ClaudeProvider();
    $response = $claudeProvider->chat($message, $selectedModel, $conversationHistory);
    
    echo json_encode([
        'success' => true,
        'response' => $response['message'],
        'tokens' => $response['tokens'] ?? 0,
        'model' => $response['model'] ?? $selectedModel
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>