<?php
require_once __DIR__ . '/../src/bootstrap.php';

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
// Get default model from cached models
$defaultModel = 'claude-3-5-haiku-20241022'; // fallback
try {
    if (class_exists('\ZeroAI\Providers\AI\Claude\ClaudeIntegration')) {
        $integration = new \ZeroAI\Providers\AI\Claude\ClaudeIntegration();
        $models = $integration->getModels();
        if (!empty($models)) {
            $defaultModel = $models[0]; // Use first available model
        }
    }
} catch (Exception $e) {
    // Use fallback
}
$selectedModel = $input['model'] ?? $defaultModel;
$conversationHistory = $input['history'] ?? [];

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Message required']);
    exit;
}

try {
    // Route to appropriate provider based on model
    if (strpos($selectedModel, 'claude') !== false) {
        // Claude models - use Claude logging
        $logger = \ZeroAI\Core\Logger::getInstance();
        $logger->logClaude('Chat handler: Claude model request', ['model' => $selectedModel, 'message_length' => strlen($message)]);
        
        if (class_exists('\ZeroAI\Providers\AI\Claude\ClaudeProvider')) {
            $provider = new \ZeroAI\Providers\AI\Claude\ClaudeProvider();
            $response = $provider->chat($message, $selectedModel, $conversationHistory, 'hybrid');
        } else {
            $logger->logClaude('Chat handler: Claude provider not available');
            echo json_encode(['success' => false, 'error' => 'Claude provider not available']);
            exit;
        }
    } elseif (strpos($selectedModel, 'gpt') !== false || strpos($selectedModel, 'openai') !== false) {
        // OpenAI models
        echo json_encode(['success' => false, 'error' => 'OpenAI provider not configured']);
        exit;
    } else {
        // Default to Claude for unknown models
        $logger = \ZeroAI\Core\Logger::getInstance();
        $logger->logClaude('Chat handler: Unknown model defaulting to Claude', ['model' => $selectedModel]);
        
        if (class_exists('\ZeroAI\Providers\AI\Claude\ClaudeProvider')) {
            $provider = new \ZeroAI\Providers\AI\Claude\ClaudeProvider();
            $response = $provider->chat($message, $selectedModel, $conversationHistory, 'hybrid');
        } else {
            $logger->logClaude('Chat handler: No AI provider available');
            echo json_encode(['success' => false, 'error' => 'No AI provider available']);
            exit;
        }
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
    // Log Claude errors with Claude logger
    if (strpos($selectedModel, 'claude') !== false || !isset($selectedModel)) {
        try {
            $logger = \ZeroAI\Core\Logger::getInstance();
            $logger->logClaude('Chat handler exception: ' . $e->getMessage(), [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'model' => $selectedModel
            ]);
        } catch (Exception $logError) {
            error_log('Chat handler error: ' . $e->getMessage());
        }
    }
    echo json_encode(['success' => false, 'error' => 'Chat service temporarily unavailable']);
}
?>
