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
        $scratchPadFile = '/app/data/scratch_pad.txt';
        
        if ($input['action'] === 'save_scratch_pad') {
            $content = $input['content'] ?? '';
            file_put_contents($scratchPadFile, $content);
            echo json_encode(['success' => true]);
            exit;
        }
        
        if ($input['action'] === 'get_scratch_pad') {
            $content = file_exists($scratchPadFile) ? file_get_contents($scratchPadFile) : '';
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
    // Use existing CloudAI class or create simple response
    if (class_exists('\ZeroAI\AI\CloudAI')) {
        $cloudAI = new \ZeroAI\AI\CloudAI();
        $response = $cloudAI->chat($message, $selectedModel);
    } else {
        // Fallback to simple response
        $response = [
            'success' => true,
            'response' => 'Claude integration is being configured. Your message: ' . $message,
            'tokens' => 0,
            'model' => $selectedModel
        ];
    }
    
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
    echo json_encode(['success' => false, 'error' => 'Chat service temporarily unavailable']);
}
?>