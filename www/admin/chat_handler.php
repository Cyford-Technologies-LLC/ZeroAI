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

// Handle scratch pad actions
if (isset($input['action'])) {
    try {
        $claude = new \ZeroAI\AI\Claude();
        
        if ($input['action'] === 'save_scratch_pad') {
            $content = $input['content'] ?? '';
            $claude->saveScratchPad($content);
            echo json_encode(['success' => true]);
            exit;
        }
        
        if ($input['action'] === 'get_scratch_pad') {
            $content = $claude->getScratchPad();
            echo json_encode(['success' => true, 'content' => $content]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

$message = $input['message'] ?? '';
$selectedModel = $input['model'] ?? 'claude-3-5-sonnet-20241022';
$conversationHistory = $input['history'] ?? [];

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Message required']);
    exit;
}

try {
    $claudeProvider = new \ZeroAI\Providers\AI\Claude\ClaudeProvider();
    $claudeMode = $input['autonomous'] ? 'autonomous' : 'hybrid';
    $response = $claudeProvider->chat($message, $selectedModel, $conversationHistory, $claudeMode);
    
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