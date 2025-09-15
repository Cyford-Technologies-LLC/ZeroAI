<?php
// ZeroAI Claude Chat Endpoint (OOP Version)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Load OOP system
require_once __DIR__ . '/autoload.php';

use Core\System;
use Core\Claude;
use Core\SystemInit;

try {
    // Initialize system
    SystemInit::initialize();
    $system = System::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \Exception('Method not allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new \Exception('Invalid JSON input');
    }
    
    $message = $input['message'] ?? '';
    $mode = $input['mode'] ?? 'hybrid';
    $model = $input['model'] ?? 'claude-3-5-sonnet-20241022';
    $history = $input['history'] ?? [];
    
    if (!$message) {
        throw new \Exception('Message required');
    }
    
    // Add mode context to message
    $modeMessages = [
        'autonomous' => '[AUTONOMOUS MODE] You have FULL access including write commands.',
        'hybrid' => '[HYBRID MODE] You have read-only access: @file, @list, @exec, @agents, @crews, @logs.',
        'chat' => '[CHAT MODE] You have read-only access: @file, @list, @agents, @crews, @logs.'
    ];
    
    $contextMessage = ($modeMessages[$mode] ?? '') . ' ' . $message;
    
    // Initialize Claude and process chat
    $claude = new Claude();
    $result = $claude->chat($contextMessage, $mode, $history);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'response' => $result['response'],
            'tokens' => $result['tokens'],
            'cost' => 0.0,
            'model' => $result['model']
        ]);
    } else {
        throw new \Exception($result['error']);
    }
    
} catch (\Exception $e) {
    $system = System::getInstance();
    $system->getLogger()->error('Chat endpoint error', [
        'error' => $e->getMessage(),
        'input' => $input ?? null
    ]);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

