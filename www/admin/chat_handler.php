<?php
require_once __DIR__ . '/includes/autoload.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
}

$message = $input['message'] ?? '';
$selectedModel = $input['model'] ?? 'claude-3-5-sonnet-20241022';
$conversationHistory = $input['history'] ?? [];

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Message required']);
    exit;
}

try {
    error_log("[CHAT_HANDLER] Starting chat request");
    error_log("[CHAT_HANDLER] Message: " . substr($message, 0, 100));
    error_log("[CHAT_HANDLER] Model: $selectedModel");
    error_log("[CHAT_HANDLER] History count: " . count($conversationHistory));
    
    $claudeProvider = new \ZeroAI\Providers\AI\Claude\ClaudeProvider();
    $claudeMode = $input['autonomous'] ? 'autonomous' : 'hybrid';
    error_log("[CHAT_HANDLER] Claude mode: $claudeMode");
    
    $response = $claudeProvider->chat($message, $selectedModel, $conversationHistory, $claudeMode);
    error_log("[CHAT_HANDLER] Response received: " . ($response['success'] ? 'SUCCESS' : 'FAILED'));
    
    if ($response['success']) {
        echo json_encode([
            'success' => true,
            'response' => $response['response'],
            'tokens' => $response['tokens'] ?? 0,
            'model' => $response['model'] ?? $selectedModel
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $response['error']]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>