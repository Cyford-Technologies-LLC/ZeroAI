<?php
session_start();
require_once __DIR__ . '/../src/autoload.php';

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    // Initialize comprehensive Claude Provider with full tool system
    $claudeProvider = new \ZeroAI\Providers\AI\Claude\ClaudeProvider();
    
    switch ($action) {
        case 'chat':
            $message = $input['message'] ?? '';
            $model = $input['model'] ?? 'claude-3-5-sonnet-20241022';
            $mode = $input['mode'] ?? 'hybrid';
            $history = $input['history'] ?? [];
            
            if (empty($message)) {
                echo json_encode(['success' => false, 'error' => 'Message required']);
                exit;
            }
            
            $response = $claudeProvider->chat($message, $model, $history, $mode);
            
            if ($response['success']) {
                echo json_encode([
                    'success' => true, 
                    'response' => $response['response'],
                    'model' => $response['model'],
                    'tokens' => $response['tokens'],
                    'cost' => $response['cost']
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => $response['error']]);
            }
            break;
            
        case 'test_connection':
            // Use basic Claude class for simple connection test
            $claude = new \ZeroAI\AI\Claude();
            $result = $claude->testConnection();
            echo json_encode($result);
            break;
            
        case 'save_scratch':
            $content = $input['content'] ?? '';
            $claude = new \ZeroAI\AI\Claude();
            $claude->saveScratchPad($content);
            echo json_encode(['success' => true]);
            break;
            
        case 'get_scratch':
            $claude = new \ZeroAI\AI\Claude();
            $content = $claude->getScratchPad();
            echo json_encode(['success' => true, 'content' => $content]);
            break;
            
        case 'get_models':
            $claude = new \ZeroAI\AI\Claude();
            $models = $claude->getAvailableModels();
            echo json_encode(['success' => true, 'models' => $models]);
            break;
            
        case 'execute_background':
            $command = $input['command'] ?? '';
            $args = $input['args'] ?? [];
            $result = $claudeProvider->executeBackgroundCommand($command, $args);
            echo json_encode(['success' => true, 'result' => $result]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>