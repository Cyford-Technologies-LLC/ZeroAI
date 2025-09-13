<?php
header('Content-Type: application/json');

require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/../src/Services/ChatService.php';
require_once __DIR__ . '/../src/Providers/AI/Claude/ClaudeProvider.php';
require_once __DIR__ . '/../src/Providers/AI/Claude/ClaudeIntegration.php';
require_once __DIR__ . '/../src/Providers/AI/Claude/ClaudeCommands.php';
require_once __DIR__ . '/../src/Providers/AI/Claude/ClaudePromptInit.php';

use ZeroAI\Services\ChatService;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \Exception('Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new \Exception('Invalid JSON input');
    }
    
    $message = $input['message'] ?? '';
    $model = $input['model'] ?? 'claude-sonnet-4-20250514';
    $autonomous = $input['autonomous'] ?? true;
    $history = $input['history'] ?? [];
    
    if (!$message) {
        throw new \Exception('Message required');
    }
    
    $chatService = new ChatService();
    $response = $chatService->processChat($message, 'claude', $model, $autonomous, $history);
    
    echo json_encode($response);
    
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}