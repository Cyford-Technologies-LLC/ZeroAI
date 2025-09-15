<?php
session_start();

// Force error logging
ini_set('log_errors', 1);
ini_set('error_log', '/app/logs/claude_debug.log');

// Create log directory
if (!is_dir('/app/logs')) {
    mkdir('/app/logs', 0755, true);
}

// Log start
file_put_contents('/app/logs/claude_debug.log', date('Y-m-d H:i:s') . " [START] Claude API started\n", FILE_APPEND);

try {
    file_put_contents('/app/logs/claude_debug.log', date('Y-m-d H:i:s') . " [INFO] Loading autoload\n", FILE_APPEND);
    require_once __DIR__ . '/../src/autoload.php';
    file_put_contents('/app/logs/claude_debug.log', date('Y-m-d H:i:s') . " [INFO] Autoload loaded successfully\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents('/app/logs/claude_debug.log', date('Y-m-d H:i:s') . " [ERROR] Autoload failed: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
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
    $logger->info('Claude API request started');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $logger->info('Claude API action', ['action' => $action]);
    
    // Initialize comprehensive Claude Provider with full tool system
    $logger->info('Initializing ClaudeProvider');
    
    if (!class_exists('\ZeroAI\Providers\AI\Claude\ClaudeProvider')) {
        $logger->error('ClaudeProvider class not found');
        throw new Exception('ClaudeProvider class not found');
    }
    
    $claudeProvider = new \ZeroAI\Providers\AI\Claude\ClaudeProvider();
    $logger->info('ClaudeProvider initialized successfully');
    
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
            $integration = new \ZeroAI\Providers\AI\Claude\ClaudeIntegration(getenv('ANTHROPIC_API_KEY'));
            $result = $integration->validateApiKey();
            echo json_encode(['success' => $result, 'message' => $result ? 'Connected' : 'Failed']);
            break;
            
        case 'save_scratch':
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
            break;
            
        case 'get_scratch':
            $db = \ZeroAI\Core\DatabaseManager::getInstance();
            $db->query("CREATE TABLE IF NOT EXISTS claude_scratch_pad (id INTEGER PRIMARY KEY, content TEXT, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
            $result = $db->query("SELECT content FROM claude_scratch_pad ORDER BY updated_at DESC LIMIT 1");
            $content = ($result && count($result) > 0) ? $result[0]['content'] : '';
            echo json_encode(['success' => true, 'content' => $content]);
            break;
            
        case 'get_models':
            $integration = new \ZeroAI\Providers\AI\Claude\ClaudeIntegration(getenv('ANTHROPIC_API_KEY'));
            $models = $integration->getModels();
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
    // Direct logging to catch all errors
    file_put_contents('/app/logs/claude_debug.log', date('Y-m-d H:i:s') . " [ERROR] Claude API Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents('/app/logs/claude_debug.log', date('Y-m-d H:i:s') . " [ERROR] File: " . $e->getFile() . " Line: " . $e->getLine() . "\n", FILE_APPEND);
    file_put_contents('/app/logs/claude_debug.log', date('Y-m-d H:i:s') . " [ERROR] Trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
    
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>