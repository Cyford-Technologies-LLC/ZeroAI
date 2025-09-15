<?php


// Ensure logs directory exists
@mkdir('/app/logs', 0755, true);

try {
    require_once __DIR__ . '/../../../src/bootstrap.php';
} catch (Exception $e) {
    // Log to multiple locations to ensure we catch the error
    $errorMsg = 'Claude API Bootstrap Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    
    // Direct file logging (most reliable)
    @file_put_contents('/app/logs/claude_debug.log', '[' . date('Y-m-d H:i:s') . '] BOOTSTRAP_ERROR: ' . $errorMsg . "\n", FILE_APPEND | LOCK_EX);
    
    // PHP error log
    error_log($errorMsg);
    
    // Set 500 status immediately
    http_response_code(500);
    header('Content-Type: application/json');
    
    // Try Logger if possible
    try {
        if (class_exists('\\ZeroAI\\Core\\Logger')) {
            $logger = \ZeroAI\Core\Logger::getInstance();
            $logger->logClaude('Bootstrap failed: ' . $e->getMessage(), ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    } catch (Exception $logError) {
        @file_put_contents('/app/logs/claude_debug.log', '[' . date('Y-m-d H:i:s') . '] LOGGER_FAILED: ' . $logError->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    }
    
    echo json_encode(['success' => false, 'error' => 'System error']);
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $logger = \ZeroAI\Core\Logger::getInstance();
    $logger->logClaude('Claude API request started');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $logger->logClaude('Claude API action', ['action' => $action]);
    
    // Initialize comprehensive Claude Provider with full tool system
    $logger->logClaude('Initializing ClaudeProvider');
    
    if (!class_exists('\ZeroAI\Providers\AI\Claude\ClaudeProvider')) {
        $logger->logClaude('ClaudeProvider class not found');
        throw new Exception('ClaudeProvider class not found');
    }
    
    $claudeProvider = new \ZeroAI\Providers\AI\Claude\ClaudeProvider();
    $logger->logClaude('ClaudeProvider initialized successfully');
    
    switch ($action) {
        case 'chat':
            $logger->logClaude('Chat action started');
            $message = $input['message'] ?? '';
            $model = $input['model'] ?? 'claude-3-5-sonnet-20241022';
            $mode = $input['mode'] ?? 'hybrid';
            $history = $input['history'] ?? [];
            
            $logger->logClaude('Chat parameters', ['message_length' => strlen($message), 'model' => $model, 'mode' => $mode, 'history_count' => count($history)]);
            
            if (empty($message)) {
                $logger->logClaude('Chat failed: empty message');
                echo json_encode(['success' => false, 'error' => 'Message required']);
                exit;
            }
            
            $logger->logClaude('Calling claudeProvider->chat()');
            $response = $claudeProvider->chat($message, $model, $history, $mode);
            $logger->logClaude('ClaudeProvider chat response', ['response_type' => gettype($response), 'success' => $response['success'] ?? 'unknown']);
            
            if ($response['success']) {
                $logger->logClaude('Chat successful, sending response');
                echo json_encode([
                    'success' => true, 
                    'response' => $response['response'],
                    'model' => $response['model'],
                    'tokens' => $response['tokens'],
                    'cost' => $response['cost']
                ]);
            } else {
                $logger->logClaude('Chat failed', ['error' => $response['error'] ?? 'unknown error']);
                echo json_encode(['success' => false, 'error' => $response['error']]);
            }
            break;
            
        case 'test_connection':
            $integration = new \ZeroAI\Providers\AI\Claude\ClaudeIntegration(getenv('ANTHROPIC_API_KEY'));
            $result = $integration->validateApiKey();
            echo json_encode(['success' => $result, 'message' => $result ? 'Connected' : 'Failed']);
            break;
            
        case 'save_scratch':
            $logger->logClaude('Save scratch action started');
            try {
                $content = $input['content'] ?? '';
                $db = \ZeroAI\Core\DatabaseManager::getInstance();
                $db->query("CREATE TABLE IF NOT EXISTS claude_scratch_pad (id INTEGER PRIMARY KEY, content TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
                $existing = $db->query("SELECT id FROM claude_scratch_pad LIMIT 1");
                if ($existing && count($existing) > 0) {
                    $db->query("UPDATE claude_scratch_pad SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1", [$content]);
                } else {
                    $db->query("INSERT INTO claude_scratch_pad (content) VALUES (?)", [$content]);
                }
                echo json_encode(['success' => true]);
            } catch (\Exception $e) {
                $logger->logClaude('Save scratch error: ' . $e->getMessage(), ['error' => $e->getMessage()]);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'get_scratch':
            $logger->logClaude('Get scratch action started');
            try {
                $db = \ZeroAI\Core\DatabaseManager::getInstance();
                $db->query("CREATE TABLE IF NOT EXISTS claude_scratch_pad (id INTEGER PRIMARY KEY, content TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
                $result = $db->query("SELECT content FROM claude_scratch_pad ORDER BY updated_at DESC LIMIT 1");
                $content = ($result && count($result) > 0) ? $result[0]['content'] : '';
                echo json_encode(['success' => true, 'content' => $content]);
            } catch (\Exception $e) {
                $logger->logClaude('Get scratch error: ' . $e->getMessage(), ['error' => $e->getMessage()]);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'save_system_prompt':
            $logger->logClaude('Save system prompt action started');
            try {
                $content = $input['content'] ?? '';
                $db = \ZeroAI\Core\DatabaseManager::getInstance();
                $db->query("CREATE TABLE IF NOT EXISTS claude_system_prompt (id INTEGER PRIMARY KEY, content TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
                $existing = $db->query("SELECT id FROM claude_system_prompt LIMIT 1");
                if ($existing && count($existing) > 0) {
                    $db->query("UPDATE claude_system_prompt SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = 1", [$content]);
                } else {
                    $db->query("INSERT INTO claude_system_prompt (content) VALUES (?)", [$content]);
                }
                echo json_encode(['success' => true]);
            } catch (\Exception $e) {
                $logger->logClaude('Save system prompt error: ' . $e->getMessage(), ['error' => $e->getMessage()]);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'get_system_prompt':
            $logger->logClaude('Get system prompt action started');
            try {
                $db = \ZeroAI\Core\DatabaseManager::getInstance();
                $db->query("CREATE TABLE IF NOT EXISTS claude_system_prompt (id INTEGER PRIMARY KEY, content TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
                $result = $db->query("SELECT content FROM claude_system_prompt ORDER BY updated_at DESC LIMIT 1");
                $content = ($result && count($result) > 0) ? $result[0]['content'] : '';
                
                // If no custom prompt, load default from file
                if (empty($content)) {
                    $defaultPromptFile = __DIR__ . '/claude_system_prompt.txt';
                    if (file_exists($defaultPromptFile)) {
                        $content = file_get_contents($defaultPromptFile);
                    }
                }
                
                echo json_encode(['success' => true, 'content' => $content]);
            } catch (\Exception $e) {
                $logger->logClaude('Get system prompt error: ' . $e->getMessage(), ['error' => $e->getMessage()]);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'get_models':
            $logger->logClaude('Get models action started');
            try {
                $integration = new \ZeroAI\Providers\AI\Claude\ClaudeIntegration();
                $modelsWithSource = $integration->getModelsWithSource();
                $logger->logClaude('Models retrieved', ['count' => count($modelsWithSource['models']), 'source' => $modelsWithSource['source'], 'models' => $modelsWithSource['models']]);
                echo json_encode(['success' => true, 'models' => $modelsWithSource['models'], 'source' => $modelsWithSource['source'], 'color' => $modelsWithSource['color']]);
            } catch (\Exception $e) {
                $logger->logClaude('Get models error: ' . $e->getMessage(), ['error' => $e->getMessage()]);
                // Fallback to basic models
                echo json_encode(['success' => true, 'models' => ['claude-3-5-sonnet-20241022', 'claude-3-5-haiku-20241022', 'claude-3-opus-20240229'], 'source' => 'Fallback']);
            }
            break;
            
        case 'execute_background':
            $command = $input['command'] ?? '';
            $args = $input['args'] ?? [];
            $result = $claudeProvider->executeBackgroundCommand($command, $args);
            echo json_encode(['success' => true, 'result' => $result]);
            break;
            
        case 'reset_system_prompt':
            $logger->logClaude('Reset system prompt action started');
            try {
                $db = \ZeroAI\Core\DatabaseManager::getInstance();
                $db->query("DELETE FROM claude_system_prompt");
                echo json_encode(['success' => true]);
            } catch (\Exception $e) {
                $logger->logClaude('Reset system prompt error: ' . $e->getMessage(), ['error' => $e->getMessage()]);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (\Exception $e) {
    // Use Logger class for all errors
    try {
        $logger = \ZeroAI\Core\Logger::getInstance();
        $logger->logClaude('Claude API Exception: ' . $e->getMessage(), [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'action' => $input['action'] ?? 'unknown'
        ]);
    } catch (\Exception $logError) {
        // Fallback only if Logger completely fails
        error_log('Claude API Error: ' . $e->getMessage());
    }
    
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>