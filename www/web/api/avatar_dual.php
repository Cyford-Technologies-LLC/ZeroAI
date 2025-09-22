<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/avatar_api_errors.log');
ob_clean(); // Clear any existing output
ob_start(); // Start fresh output buffering
ini_set('max_execution_time', 300);
set_time_limit(300);


// ... rest of existing code ...


require_once '../../src/autoload.php';

use ZeroAI\Providers\AI\Local\AvatarManager;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    error_log('=== AVATAR DUAL API REQUEST ===');
    error_log('Method: ' . $_SERVER['REQUEST_METHOD']);
    error_log('Query: ' . $_SERVER['QUERY_STRING']);
    error_log('Headers: ' . json_encode(getallheaders()));
    
    $avatarManager = new AvatarManager(true); // Debug mode ON
    
    $action = $_GET['action'] ?? 'generate';
    $mode = $_GET['mode'] ?? 'simple';
    
    error_log("Action: $action, Mode: $mode");
    
    switch ($action) {
        case 'generate':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required for generation');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Invalid JSON input');
            }
            
            $prompt = $input['prompt'] ?? '';
            if (empty($prompt)) {
                throw new Exception('Prompt is required');
            }
            
            error_log("Generating avatar - Mode: $mode, Prompt: " . substr($prompt, 0, 50));
            
            if ($mode === 'sadtalker') {
                $result = $avatarManager->generateSadTalker($prompt, $input['options'] ?? []);
            } else {
                $result = $avatarManager->generateSimple($prompt, $input['options'] ?? []);
            }

            ob_end_clean();
            
            // Return video data
            header('Content-Type: ' . $result['content_type']);
            header('Content-Length: ' . $result['size']);
            header('X-Avatar-Mode: ' . $mode);
            header('X-Avatar-Size: ' . $result['size']);
            
            echo $result['data'];
            exit;
            
        case 'status':
            error_log('Getting avatar service status');
            $result = $avatarManager->getStatus();
            echo json_encode($result);
            break;
            
        case 'logs':
            error_log('Getting avatar service logs');
            $result = $avatarManager->getLogs();
            echo json_encode($result);
            break;
            
        case 'test':
            error_log('Testing avatar service connection');
            $result = $avatarManager->testConnection();
            echo json_encode($result);
            break;
            
        case 'php_errors':
            error_log('Getting PHP errors');
            $errors = $avatarManager->getPhpErrors();
            echo json_encode(['errors' => $errors]);
            break;
            
        case 'clear_errors':
            error_log('Clearing PHP errors');
            $cleared = $avatarManager->clearPhpErrors();
            echo json_encode(['cleared' => $cleared]);
            break;
            
        case 'server_info':
            error_log('Getting server connection info');
            $currentPeer = $avatarManager->getCurrentPeer();
            $availablePeers = $avatarManager->getAvailablePeers();
            echo json_encode([
                'current_peer' => $currentPeer,
                'available_peers' => $availablePeers
            ]);
            break;
            
        default:
            throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log('Avatar API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'request_info' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'query' => $_SERVER['QUERY_STRING'],
            'action' => $action ?? 'unknown',
            'mode' => $mode ?? 'unknown'
        ]
    ]);
}
?>